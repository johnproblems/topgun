<?php

namespace App\Data;

use App\Models\EnterpriseLicense;

class LicenseValidationResult
{
    public function __construct(
        public readonly bool $isValid,
        public readonly string $message,
        public readonly ?EnterpriseLicense $license = null,
        public readonly array $violations = [],
        public readonly array $metadata = []
    ) {}

    public function isValid(): bool
    {
        return $this->isValid;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getLicense(): ?EnterpriseLicense
    {
        return $this->license;
    }

    public function getViolations(): array
    {
        return $this->violations;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function hasViolations(): bool
    {
        return ! empty($this->violations);
    }

    public function toArray(): array
    {
        return [
            'is_valid' => $this->isValid,
            'message' => $this->message,
            'license_id' => $this->license?->id,
            'violations' => $this->violations,
            'metadata' => $this->metadata,
        ];
    }
}
