<?php

namespace App\DTOs;

class PhysicalFormData
{
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly ?string $description,
        public readonly bool $is_active,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            code: trim((string) $data['code']),
            name: trim((string) $data['name']),
            description: empty($data['description']) ? null : trim((string) $data['description']),
            is_active: (bool) ($data['is_active'] ?? true),
        );
    }
}
