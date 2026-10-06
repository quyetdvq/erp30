<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;

final class Auth extends ApiController
{
    public function options(): ResponseInterface
    {
        return $this->respondNoContent();
    }

    public function login(): ResponseInterface
    {
        $payload = $this->jsonPayload();
        $identity = trim((string) ($payload['email'] ?? $payload['username'] ?? ''));
        $password = (string) ($payload['password'] ?? '');

        if ($identity === '' || $password === '') {
            return $this->respondError('INVALID_CREDENTIALS', 422, [[
                'field' => 'password',
                'code' => 'INVALID_CREDENTIALS',
                'message' => \App\Infrastructure\Api\ApiTransport::message('INVALID_CREDENTIALS', 422),
            ]]);
        }

        $authenticator = auth('session')->getAuthenticator();
        if ($authenticator instanceof Session) {
            $authenticator->remember((bool) ($payload['remember'] ?? false));
        }
        $result = $authenticator->attempt(str_contains($identity, '@')
                ? ['email' => $identity, 'password' => $password]
                : ['username' => $identity, 'password' => $password]);

        if (! $result->isOK()) {
            return $this->respondError('INVALID_CREDENTIALS', 401);
        }

        $user = auth()->user();

        return $this->respondData([
            'user' => $user instanceof User ? $this->userPayload($user) : null,
        ]);
    }

    public function logout(): ResponseInterface
    {
        auth()->logout();

        return $this->respondData(['ok' => true]);
    }

    public function me(): ResponseInterface
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return $this->respondError('UNAUTHENTICATED', 401);
        }

        return $this->respondData(['user' => $this->userPayload($user)]);
    }

    public function profile(): ResponseInterface
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return $this->respondError('UNAUTHENTICATED', 401);
        }

        $payload = $this->jsonPayload();
        $username = trim((string) ($payload['username'] ?? ''));
        $email = trim((string) ($payload['email'] ?? ''));
        $password = (string) ($payload['password'] ?? '');

        if ($username === '' || $email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->respondError('INVALID_PROFILE', 422, [[
                'field' => 'email',
                'code' => 'INVALID_PROFILE',
                'message' => \App\Infrastructure\Api\ApiTransport::message('INVALID_PROFILE', 422),
            ]]);
        }

        $users = model(UserModel::class);
        $user->username = $username;
        $user->setEmail($email);
        if ($password !== '') {
            $user->setPassword($password);
        }

        try {
            $users->save($user);
        } catch (\Throwable $exception) {
            return $this->respondError('PROFILE_UPDATE_FAILED', 422);
        }

        return $this->respondData(['user' => $this->userPayload($user)]);
    }

    /** @return array<string, mixed> */
    private function userPayload(User $user): array
    {
        $groups = $user->getGroups() ?? [];

        return [
            'id' => (int) $user->id,
            'username' => $user->username,
            'email' => $user->getEmail(),
            'active' => (bool) $user->active,
            'status' => $user->status,
            'groups' => array_values(array_map('strval', $groups)),
        ];
    }
}
