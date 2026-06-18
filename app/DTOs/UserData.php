<?php

namespace App\DTOs;

use App\Enums\UserRole;

class UserData
{
    public function __construct(
        public readonly string $name,
        public readonly string $username,
        public readonly string $email,
        public readonly UserRole $role,
        public readonly ?string $password = null,
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            name: $data['name'],
            username: $data['username'],
            email: $data['email'],
            role: isset($data['role']) ? UserRole::from($data['role']) : UserRole::ADMIN_RNI,
            password: $data['password'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'role' => $this->role->value,
            'password' => $this->password,
        ];
    }
}
