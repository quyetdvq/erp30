<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Entities\User;

/** Shield is the sole source of session identity. No second user store. */
final class IdentityService
{
    /** @param array<string, mixed> $credentials */
    public function login(array $credentials): ?User
    {
        $identifier = trim((string) ($credentials['email'] ?? $credentials['username'] ?? ''));
        $password = $credentials['password'] ?? '';
        if ($identifier === '' || ! is_string($password) || $password === '') {
            throw new \InvalidArgumentException('Missing username/email or password.');
        }

        $authenticator = auth('session')->getAuthenticator();
        if ($authenticator instanceof Session) {
            $authenticator->remember((bool) ($credentials['remember'] ?? false));
        }
        $result = $authenticator->attempt(str_contains($identifier, '@')
            ? ['email' => $identifier, 'password' => $password]
            : ['username' => $identifier, 'password' => $password]);
        if (! $result->isOK()) {
            return null;
        }
        return $this->currentUser();
    }

    public function currentUser(): ?User
    {
        $user = auth()->user();
        return $user instanceof User ? $user : null;
    }

    public function logout(): void
    {
        auth()->logout();
    }
}
