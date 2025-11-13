<?php

namespace App\Services\Enterprise;

use Illuminate\Support\Facades\Log;

class CssValidationService
{
    private const DANGEROUS_PATTERNS = [
        '@import',
        'expression(',
        'javascript:',
        'vbscript:',
        'behavior:',
        'data:text/html',
        '-moz-binding',
    ];

    public function sanitize(string $css): string
    {
        // 1. Remove dangerous patterns
        $sanitized = $this->stripDangerousPatterns($css);

        // 2. Parse and validate CSS (if sabberworm is available)
        try {
            if (class_exists(\Sabberworm\CSS\Parser::class)) {
                $parsed = $this->parseAndValidate($sanitized);
                return $parsed;
            }

            // Fallback: return sanitized CSS if parser not available
            return $sanitized;
        } catch (\Exception $e) {
            Log::warning('Invalid custom CSS provided', [
                'error' => $e->getMessage(),
                'css_length' => strlen($css),
            ]);

            return '/* Invalid CSS removed - please check syntax */';
        }
    }

    private function stripDangerousPatterns(string $css): string
    {
        // Remove HTML tags
        $css = strip_tags($css);

        // Remove dangerous CSS patterns
        foreach (self::DANGEROUS_PATTERNS as $pattern) {
            $css = str_ireplace($pattern, '', $css);
        }

        // Remove potential XSS vectors
        $css = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $css);
        $css = preg_replace('/on\w+\s*=\s*["\'].*?["\']/is', '', $css);

        return $css;
    }

    private function parseAndValidate(string $css): string
    {
        if (!class_exists(\Sabberworm\CSS\Parser::class)) {
            return $css;
        }

        $parser = new \Sabberworm\CSS\Parser($css);
        $document = $parser->parse();

        // Remove any @import rules that might have slipped through
        $this->removeImports($document);

        return $document->render();
    }

    private function removeImports(\Sabberworm\CSS\CSSList\Document $document): void
    {
        foreach ($document->getContents() as $item) {
            if ($item instanceof \Sabberworm\CSS\RuleSet\AtRuleSet) {
                if (stripos($item->atRuleName(), 'import') !== false) {
                    $document->remove($item);
                }
            }
        }
    }

    public function validate(string $css): array
    {
        $errors = [];

        // Check for dangerous patterns
        foreach (self::DANGEROUS_PATTERNS as $pattern) {
            if (stripos($css, $pattern) !== false) {
                $errors[] = "Dangerous pattern detected: {$pattern}";
            }
        }

        // Validate CSS syntax (if parser available)
        if (class_exists(\Sabberworm\CSS\Parser::class)) {
            try {
                $parser = new \Sabberworm\CSS\Parser($css);
                $parser->parse();
            } catch (\Exception $e) {
                $errors[] = "CSS syntax error: {$e->getMessage()}";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
