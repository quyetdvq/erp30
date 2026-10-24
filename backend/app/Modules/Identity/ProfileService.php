<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use CodeIgniter\Shield\Entities\User;

/** Self-service profile only; no admin capability here. */
final class ProfileService
{
    /** @param array<string, mixed> $payload */
    public function update(User $user, array $payload): void
    {
        if ($payload === [] || array_diff(array_keys($payload), ['username', 'email', 'password', 'name']) !== []) {
            throw new \InvalidArgumentException('Invalid profile fields.');
        }
        $username = array_key_exists('username', $payload) ? $payload['username'] : $user->username;
        $email = array_key_exists('email', $payload) ? $payload['email'] : $user->getEmail();
        $username = is_string($username) ? trim($username) : $username;
        $email = is_string($email) ? trim($email) : $email;
        $password = $payload['password'] ?? '';
        if (! is_string($username) || $username === ''
            || ! is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false
            || ! is_string($password)) {
            throw new \InvalidArgumentException('Invalid profile values.');
        }
        if (array_key_exists('name', $payload)) {
            $user->full_name = DisplayName::validate($payload['name']);
        }
        if (array_key_exists('username', $payload)) {
            $user->username = $username;
        }
        if (array_key_exists('email', $payload)) {
            $user->setEmail($email);
        }
        if ($password !== '') {
            $user->setPassword($password);
        }
        model(UserModel::class)->save($user);
    }
}
