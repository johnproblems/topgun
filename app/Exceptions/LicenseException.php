<?php

namespace App\Exceptions;

use Exception;

class LicenseException extends Exception
{
    public static function notFound(): self
    {
        return new self('License not found');
    }

    public static function expired(): self
    {
        return new self('License has expired');
    }

    public static function revoked(): self
    {
        return new self('License has been revoked');
    }

    public static function suspended(?string $reason = null): self
    {
        $message = 'License is suspended';
        if ($reason) {
            $message .= ': '.$reason;
        }

        return new self($message);
    }

    public static function domainNotAuthorized(string $domain): self
    {
        return new self("Domain '{$domain}' is not authorized for this license");
    }

    public static function usageLimitExceeded(array $violations): self
    {
        $messages = array_column($violations, 'message');

        return new self('Usage limits exceeded: '.implode(', ', $messages));
    }

    public static function validationFailed(string $reason): self
    {
        return new self('License validation failed: '.$reason);
    }

    public static function generationFailed(): self
    {
        return new self('Failed to generate license key');
    }
}
