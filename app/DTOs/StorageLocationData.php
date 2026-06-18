<?php

namespace App\DTOs;

class StorageLocationData
{
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly ?string $type,
        public readonly ?int $parent_id,
        public readonly ?string $description,
        public readonly bool $is_active,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            code: trim((string) $data['code']),
            name: trim((string) $data['name']),
            type: !empty($data['type']) ? trim((string) $data['type']) : null,
            parent_id: isset($data['parent_id']) && $data['parent_id'] !== '' ? (int) $data['parent_id'] : null,
            description: !empty($data['description']) ? trim((string) $data['description']) : null,
            is_active: (bool) ($data['is_active'] ?? true),
        );
    }
}
