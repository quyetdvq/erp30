<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/m63b1/M63B1AvatarPlan.php';

/** Pure safe fixture gates for delegated user-avatar DEV HTTP testing. */
final class M63B2AvatarPlan
{
    /** @param array<string,mixed> $adminMe @param array<string,mixed> $ownerMe
     * @return array{admin:array<string,mixed>,owner:array<string,mixed>,admin_id:string}
     */
    public static function actors(array $adminMe, array $ownerMe, string $targetId): array
    {
        $targetId = M63B1AvatarPlan::userId($targetId);
        $owner = M63B1AvatarPlan::ownEmptyActor($ownerMe, $targetId);
        $admin = $adminMe['json']['data']['user'] ?? null;
        if (($adminMe['status'] ?? null) !== 200 || !is_array($admin)
            || ($admin['active'] ?? null) !== true
            || !is_array($admin['base_permissions'] ?? null)) {
            throw new RuntimeException('Active authenticated admin required.');
        }
        $adminId = M63B1AvatarPlan::userId((string) ($admin['id'] ?? ''));
        if ($adminId === $targetId || !in_array('user.view', $admin['base_permissions'], true)
            || !in_array('user.edit', $admin['base_permissions'], true)) {
            throw new RuntimeException('Distinct delegated admin with user.view and user.edit is required.');
        }
        if (($owner['username'] ?? null) === ($admin['username'] ?? null)) {
            throw new RuntimeException('Admin and target identities must differ.');
        }
        return ['admin' => $admin, 'owner' => $owner, 'admin_id' => $adminId];
    }

    /** @param array<string,mixed> $response */
    public static function allowedProbe(array $response, string $locale): string
    {
        return M63B1AvatarPlan::negative($response, 'USER_AVATAR_INVALID_CONTENT', $locale);
    }

    /** @param array<string,mixed> $response */
    public static function deniedProbe(array $response, string $locale): string
    {
        $status = $response['status'] ?? null;
        $code = $response['json']['error']['code'] ?? null;
        $expected = ['USER_NOT_FOUND' => 404, 'USER_AUTHORIZATION_DENIED' => 403];
        if (!is_string($code) || !isset($expected[$code]) || $expected[$code] !== $status
            || ($response['language'] ?? null) !== $locale
            || !is_string($response['json']['error']['message'] ?? null)
            || $response['json']['error']['message'] === '') {
            throw new RuntimeException('Non-owner must be denied before multipart parsing.');
        }
        return $code;
    }

    /** @param array<string,mixed> $before @param array<string,mixed> $after */
    public static function unchangedProfile(array $before, array $after): void
    {
        foreach (['id', 'username', 'email', 'name', 'active', 'status', 'groups', 'base_permissions'] as $field) {
            if (($before[$field] ?? null) !== ($after[$field] ?? null)) {
                throw new RuntimeException('Unrelated Shield profile field changed during Avatar QA.');
            }
        }
    }

    /** @param array<string,mixed> $before @param array<string,mixed> $after */
    public static function unchangedAdminAvatar(array $before, array $after): void
    {
        self::unchangedProfile($before, $after);
        if (($before['has_avatar'] ?? null) !== ($after['has_avatar'] ?? null)
            || ($before['avatar_url'] ?? null) !== ($after['avatar_url'] ?? null)) {
            throw new RuntimeException('Admin avatar was modified by forbidden nonowner probe.');
        }
    }
}
