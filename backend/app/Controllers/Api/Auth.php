<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;

final class Auth extends BaseController
{
    public function options(): ResponseInterface
    {
        return $this->response->setStatusCode(204);
    }

    public function login(): ResponseInterface
    {
        $payload = $this->jsonPayload();
        $identity = trim((string) ($payload['email'] ?? $payload['username'] ?? ''));
        $password = (string) ($payload['password'] ?? '');

        if ($identity === '' || $password === '') {
            return $this->response->setStatusCode(422)->setJSON([
                'error' => 'INVALID_CREDENTIALS',
                'message' => 'Email/tài khoản và mật khẩu là bắt buộc.',
            ]);
        }

        $authenticator = auth('session')->getAuthenticator();
        if ($authenticator instanceof Session) {
            $authenticator->remember((bool) ($payload['remember'] ?? false));
        }
        $result = $authenticator->attempt(str_contains($identity, '@')
                ? ['email' => $identity, 'password' => $password]
                : ['username' => $identity, 'password' => $password]);

        if (! $result->isOK()) {
            return $this->response->setStatusCode(401)->setJSON([
                'error' => 'INVALID_CREDENTIALS',
                'message' => $result->reason(),
            ]);
        }

        $user = auth()->user();

        return $this->response->setJSON([
            'user' => $user instanceof User ? $this->userPayload($user) : null,
        ]);
    }

    public function logout(): ResponseInterface
    {
        auth()->logout();

        return $this->response->setJSON(['ok' => true]);
    }

    public function me(): ResponseInterface
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return $this->response->setStatusCode(401)->setJSON(['error' => 'UNAUTHENTICATED']);
        }

        return $this->response->setJSON(['user' => $this->userPayload($user)]);
    }

    public function profile(): ResponseInterface
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return $this->response->setStatusCode(401)->setJSON(['error' => 'UNAUTHENTICATED']);
        }

        $payload = $this->jsonPayload();
        $username = trim((string) ($payload['username'] ?? ''));
        $email = trim((string) ($payload['email'] ?? ''));
        $password = (string) ($payload['password'] ?? '');

        if ($username === '' || $email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->response->setStatusCode(422)->setJSON([
                'error' => 'INVALID_PROFILE',
                'message' => 'Tên tài khoản và email hợp lệ là bắt buộc.',
            ]);
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
            return $this->response->setStatusCode(422)->setJSON([
                'error' => 'PROFILE_UPDATE_FAILED',
                'message' => $exception->getMessage(),
            ]);
        }

        return $this->response->setJSON(['user' => $this->userPayload($user)]);
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
