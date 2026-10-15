<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Authorization\EffectiveProfileResolver;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Entities\User;
use App\Models\UserModel;
use App\Services\UserDisplayName;

final class Auth extends ApiController
{
    public function options(): ResponseInterface
    {
        return $this->respondNoContent();
    }

    public function csrf(): ResponseInterface
    {
        $security = service('security');

        return $this->respondData([
            'csrf_token' => $security->getHash(),
            'header_name' => $security->getHeaderName(),
        ]);
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
        foreach (array_keys($payload) as $field) {
            if (! in_array($field, ['username', 'email', 'password', 'name'], true)) {
                return $this->respondError('INVALID_PROFILE', 422);
            }
        }
        if ($payload === []) {
            return $this->respondError('INVALID_PROFILE', 422);
        }
        $username = array_key_exists('username', $payload) ? $payload['username'] : $user->username;
        $email = array_key_exists('email', $payload) ? $payload['email'] : $user->getEmail();
        $username = is_string($username) ? trim($username) : $username;
        $email = is_string($email) ? trim($email) : $email;
        $password = $payload['password'] ?? '';
        if (! is_string($username) || trim($username) === '' || ! is_string($email)
            || filter_var($email, FILTER_VALIDATE_EMAIL) === false || ! is_string($password)) {
            return $this->respondError('INVALID_PROFILE', 422);
        }
        try {
            if (array_key_exists('name', $payload)) {
                $user->full_name = UserDisplayName::normalize($payload['name']);
            }
            if (array_key_exists('username', $payload)) {
                $user->username = trim($username);
            }
            if (array_key_exists('email', $payload)) {
                $user->setEmail(trim($email));
            }
            if ($password !== '') {
                $user->setPassword($password);
            }
            if (! model(UserModel::class)->save($user)) {
                return $this->respondError('PROFILE_UPDATE_FAILED', 422);
            }
        } catch (\App\Exceptions\DomainException $exception) {
            return $this->respondError('INVALID_PROFILE', 422);
        } catch (\Throwable $exception) {
            return $this->respondError('PROFILE_UPDATE_FAILED', 422);
        }

        return $this->respondData(['user' => $this->userPayload($user)]);
    }

    /** @return array<string, mixed> */
    private function userPayload(User $user): array
    {
        $groups = $user->getGroups() ?? [];
        $basePermissions = (new EffectiveProfileResolver())->resolve($user)->keys();

        return [
            'id' => (int) $user->id,
            'username' => $user->username,
            'email' => $user->getEmail(),
            'name' => $user->full_name ?? null,
            'has_avatar' => is_string($user->avatar_filename ?? null) && $user->avatar_filename !== '',
            'active' => (bool) $user->active,
            'status' => $user->status,
            'groups' => array_values(array_map('strval', $groups)),
            'base_permissions' => $basePermissions,
        ];
    }
}
