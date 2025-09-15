<?php

namespace App\Services\Enterprise;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DomainValidationService
{
    protected const DNS_RECORD_TYPES = ['A', 'AAAA', 'CNAME'];
    protected const SSL_PORT = 443;
    protected const DNS_TIMEOUT = 5;
    protected const SSL_TIMEOUT = 10;

    /**
     * Validate DNS configuration for a domain
     */
    public function validateDns(string $domain): array
    {
        $results = [
            'valid' => false,
            'records' => [],
            'errors' => [],
            'warnings' => [],
        ];

        try {
            // Check various DNS record types
            foreach (self::DNS_RECORD_TYPES as $type) {
                $records = $this->getDnsRecords($domain, $type);
                if (!empty($records)) {
                    $results['records'][$type] = $records;
                }
            }

            // Check if domain resolves to an IP
            $ip = gethostbyname($domain);
            if ($ip !== $domain) {
                $results['valid'] = true;
                $results['resolved_ip'] = $ip;

                // Verify the IP points to our servers (if configured)
                $this->verifyServerPointing($ip, $results);
            } else {
                $results['errors'][] = 'Domain does not resolve to any IP address';
            }

            // Check for wildcard DNS if subdomain
            if (substr_count($domain, '.') > 1) {
                $this->checkWildcardDns($domain, $results);
            }

            // Check nameservers
            $this->checkNameservers($domain, $results);

        } catch (\Exception $e) {
            $results['errors'][] = 'DNS validation error: ' . $e->getMessage();
            Log::error('DNS validation failed', [
                'domain' => $domain,
                'error' => $e->getMessage(),
            ]);
        }

        return $results;
    }

    /**
     * Get DNS records for a domain
     */
    protected function getDnsRecords(string $domain, string $type): array
    {
        $records = [];

        switch ($type) {
            case 'A':
                $dnsRecords = dns_get_record($domain, DNS_A);
                break;
            case 'AAAA':
                $dnsRecords = dns_get_record($domain, DNS_AAAA);
                break;
            case 'CNAME':
                $dnsRecords = dns_get_record($domain, DNS_CNAME);
                break;
            default:
                $dnsRecords = [];
        }

        foreach ($dnsRecords as $record) {
            $records[] = [
                'type' => $type,
                'value' => $record['ip'] ?? $record['ipv6'] ?? $record['target'] ?? null,
                'ttl' => $record['ttl'] ?? null,
            ];
        }

        return $records;
    }

    /**
     * Verify if IP points to our servers
     */
    protected function verifyServerPointing(string $ip, array &$results): void
    {
        // Get configured server IPs from environment or config
        $serverIps = config('whitelabel.server_ips', []);

        if (empty($serverIps)) {
            $results['warnings'][] = 'Server IP verification not configured';
            return;
        }

        if (in_array($ip, $serverIps)) {
            $results['server_pointing'] = true;
            $results['info'][] = 'Domain correctly points to application servers';
        } else {
            $results['warnings'][] = 'Domain does not point to application servers';
            $results['server_pointing'] = false;
        }
    }

    /**
     * Check wildcard DNS configuration
     */
    protected function checkWildcardDns(string $domain, array &$results): void
    {
        $parts = explode('.', $domain);
        array_shift($parts); // Remove subdomain
        $parentDomain = implode('.', $parts);

        $wildcardDomain = '*.' . $parentDomain;
        $ip = gethostbyname('test-' . uniqid() . '.' . $parentDomain);

        if ($ip !== 'test-' . uniqid() . '.' . $parentDomain) {
            $results['wildcard_dns'] = true;
            $results['info'][] = 'Wildcard DNS is configured for parent domain';
        }
    }

    /**
     * Check nameservers
     */
    protected function checkNameservers(string $domain, array &$results): void
    {
        $nsRecords = dns_get_record($domain, DNS_NS);

        if (!empty($nsRecords)) {
            $results['nameservers'] = array_map(function ($record) {
                return $record['target'] ?? null;
            }, $nsRecords);
        }
    }

    /**
     * Validate SSL certificate for a domain
     */
    public function validateSsl(string $domain): array
    {
        $results = [
            'valid' => false,
            'certificate' => [],
            'errors' => [],
            'warnings' => [],
        ];

        try {
            // Get SSL certificate information
            $certInfo = $this->getSslCertificate($domain);

            if ($certInfo) {
                $results['certificate'] = $certInfo;

                // Validate certificate
                $validation = $this->validateCertificate($certInfo, $domain);
                $results = array_merge($results, $validation);
            } else {
                $results['errors'][] = 'Could not retrieve SSL certificate';
            }

            // Check SSL/TLS configuration
            $this->checkSslConfiguration($domain, $results);

        } catch (\Exception $e) {
            $results['errors'][] = 'SSL validation error: ' . $e->getMessage();
            Log::error('SSL validation failed', [
                'domain' => $domain,
                'error' => $e->getMessage(),
            ]);
        }

        return $results;
    }

    /**
     * Get SSL certificate information
     */
    protected function getSslCertificate(string $domain): ?array
    {
        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ]);

        $stream = @stream_socket_client(
            "ssl://{$domain}:" . self::SSL_PORT,
            $errno,
            $errstr,
            self::SSL_TIMEOUT,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$stream) {
            return null;
        }

        $params = stream_context_get_params($stream);
        fclose($stream);

        if (!isset($params['options']['ssl']['peer_certificate'])) {
            return null;
        }

        $cert = $params['options']['ssl']['peer_certificate'];
        $certInfo = openssl_x509_parse($cert);

        if (!$certInfo) {
            return null;
        }

        return [
            'subject' => $certInfo['subject']['CN'] ?? null,
            'issuer' => $certInfo['issuer']['O'] ?? null,
            'valid_from' => date('Y-m-d H:i:s', $certInfo['validFrom_time_t']),
            'valid_to' => date('Y-m-d H:i:s', $certInfo['validTo_time_t']),
            'san' => $this->extractSan($certInfo),
            'signature_algorithm' => $certInfo['signatureTypeSN'] ?? null,
        ];
    }

    /**
     * Extract Subject Alternative Names from certificate
     */
    protected function extractSan(array $certInfo): array
    {
        $san = [];

        if (isset($certInfo['extensions']['subjectAltName'])) {
            $sanString = $certInfo['extensions']['subjectAltName'];
            $parts = explode(',', $sanString);

            foreach ($parts as $part) {
                $part = trim($part);
                if (strpos($part, 'DNS:') === 0) {
                    $san[] = substr($part, 4);
                }
            }
        }

        return $san;
    }

    /**
     * Validate certificate details
     */
    protected function validateCertificate(array $certInfo, string $domain): array
    {
        $results = [
            'valid' => true,
            'checks' => [],
        ];

        // Check if certificate is valid for domain
        $validForDomain = false;
        if ($certInfo['subject'] === $domain || $certInfo['subject'] === '*.' . substr($domain, strpos($domain, '.') + 1)) {
            $validForDomain = true;
        } elseif (in_array($domain, $certInfo['san'])) {
            $validForDomain = true;
        } elseif (in_array('*.' . substr($domain, strpos($domain, '.') + 1), $certInfo['san'])) {
            $validForDomain = true;
        }

        $results['checks']['domain_match'] = $validForDomain;
        if (!$validForDomain) {
            $results['errors'][] = 'Certificate is not valid for this domain';
            $results['valid'] = false;
        }

        // Check expiration
        $validTo = strtotime($certInfo['valid_to']);
        $now = time();
        $daysUntilExpiry = ($validTo - $now) / 86400;

        $results['checks']['days_until_expiry'] = round($daysUntilExpiry);

        if ($daysUntilExpiry < 0) {
            $results['errors'][] = 'Certificate has expired';
            $results['valid'] = false;
        } elseif ($daysUntilExpiry < 30) {
            $results['warnings'][] = 'Certificate expires in less than 30 days';
        }

        // Check if certificate is not yet valid
        $validFrom = strtotime($certInfo['valid_from']);
        if ($validFrom > $now) {
            $results['errors'][] = 'Certificate is not yet valid';
            $results['valid'] = false;
        }

        // Check issuer (warn if self-signed)
        if (isset($certInfo['issuer']) && stripos($certInfo['issuer'], 'Let\'s Encrypt') === false
            && stripos($certInfo['issuer'], 'DigiCert') === false
            && stripos($certInfo['issuer'], 'GlobalSign') === false
            && stripos($certInfo['issuer'], 'Sectigo') === false) {
            $results['warnings'][] = 'Certificate issuer is not a well-known CA';
        }

        return $results;
    }

    /**
     * Check SSL/TLS configuration
     */
    protected function checkSslConfiguration(string $domain, array &$results): void
    {
        try {
            // Test HTTPS connectivity
            $response = Http::timeout(self::SSL_TIMEOUT)
                ->withOptions(['verify' => false])
                ->get("https://{$domain}");

            if ($response->successful()) {
                $results['https_accessible'] = true;

                // Check for security headers
                $this->checkSecurityHeaders($response->headers(), $results);
            } else {
                $results['warnings'][] = 'HTTPS endpoint returned non-200 status code';
            }

        } catch (\Exception $e) {
            $results['warnings'][] = 'Could not test HTTPS connectivity';
        }
    }

    /**
     * Check security headers
     */
    protected function checkSecurityHeaders(array $headers, array &$results): void
    {
        $securityHeaders = [
            'Strict-Transport-Security' => 'HSTS',
            'X-Content-Type-Options' => 'X-Content-Type-Options',
            'X-Frame-Options' => 'X-Frame-Options',
            'Content-Security-Policy' => 'CSP',
        ];

        $results['security_headers'] = [];

        foreach ($securityHeaders as $header => $name) {
            $headerLower = strtolower($header);
            $found = false;

            foreach ($headers as $key => $value) {
                if (strtolower($key) === $headerLower) {
                    $results['security_headers'][$name] = true;
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $results['security_headers'][$name] = false;
                $results['warnings'][] = "Missing security header: {$name}";
            }
        }
    }

    /**
     * Verify domain ownership via DNS TXT record
     */
    public function verifyDomainOwnership(string $domain, string $verificationToken): bool
    {
        $txtRecords = dns_get_record($domain, DNS_TXT);

        foreach ($txtRecords as $record) {
            if (isset($record['txt']) && $record['txt'] === "coolify-verify={$verificationToken}") {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate domain verification token
     */
    public function generateVerificationToken(string $domain, string $organizationId): string
    {
        return hash('sha256', $domain . $organizationId . config('app.key'));
    }

    /**
     * Check if domain is already in use
     */
    public function isDomainAvailable(string $domain): bool
    {
        // Check if domain is already configured for another organization
        $existing = \App\Models\WhiteLabelConfig::whereJsonContains('custom_domains', $domain)->first();

        return $existing === null;
    }

    /**
     * Perform comprehensive domain validation
     */
    public function performComprehensiveValidation(string $domain, string $organizationId): array
    {
        $results = [
            'domain' => $domain,
            'timestamp' => now()->toIso8601String(),
            'checks' => [],
        ];

        // Check domain availability
        $results['checks']['available'] = $this->isDomainAvailable($domain);
        if (!$results['checks']['available']) {
            $results['valid'] = false;
            $results['errors'][] = 'Domain is already in use by another organization';
            return $results;
        }

        // Validate DNS
        $dnsResults = $this->validateDns($domain);
        $results['checks']['dns'] = $dnsResults;

        // Validate SSL
        $sslResults = $this->validateSsl($domain);
        $results['checks']['ssl'] = $sslResults;

        // Check domain ownership
        $verificationToken = $this->generateVerificationToken($domain, $organizationId);
        $results['checks']['ownership'] = $this->verifyDomainOwnership($domain, $verificationToken);
        $results['verification_token'] = $verificationToken;

        // Determine overall validity
        $results['valid'] = $dnsResults['valid'] &&
                          $sslResults['valid'] &&
                          $results['checks']['available'];

        // Add recommendations
        $this->addRecommendations($results);

        return $results;
    }

    /**
     * Add recommendations based on validation results
     */
    protected function addRecommendations(array &$results): void
    {
        $recommendations = [];

        if (!$results['checks']['ownership']) {
            $recommendations[] = [
                'type' => 'dns_txt',
                'message' => 'Add TXT record with value: coolify-verify=' . $results['verification_token'],
            ];
        }

        if (!$results['checks']['ssl']['valid']) {
            $recommendations[] = [
                'type' => 'ssl',
                'message' => 'Install a valid SSL certificate for the domain',
            ];
        }

        if (isset($results['checks']['dns']['server_pointing']) && !$results['checks']['dns']['server_pointing']) {
            $recommendations[] = [
                'type' => 'dns_a',
                'message' => 'Point domain A record to application servers',
            ];
        }

        $results['recommendations'] = $recommendations;
    }
}