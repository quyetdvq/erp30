<?php

declare(strict_types=1);

namespace App\Authorization;

final readonly class ShieldIdentity
{
    public function __construct(
        public int $userId,
        public string $username,
        public bool $active,
        public string $status,
    ) {
    }

    public function isOperational(): bool
    {
        return $this->active && ! in_array(strtolower($this->status), [
            'inactive', 'locked', 'suspended', 'banned', 'disabled',
        ], true);
    }

    /** @return array{user_id: int, username: string, active: bool, status: string} */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'username' => $this->username,
            'active' => $this->active,
            'status' => $this->status,
        ];
    }
}
