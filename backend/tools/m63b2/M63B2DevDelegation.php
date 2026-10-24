<?php

declare(strict_types=1);

require_once __DIR__.'/M63B2AvatarPlan.php';
require_once __DIR__.'/M63B2AvatarAudit.php';
require_once dirname(__DIR__).'/m63b1/M63B1AvatarTransport.php';

/** DEV-only delegated admin Avatar QA; no direct SQL writes or arbitrary hosts. */
final class M63B2DevDelegation
{
    /** @return array<string,mixed> */
    public function run(string $id, string $adminUsername, string $adminPassword,
        string $targetUsername, string $targetPassword, bool $apply): array
    {
        $id = M63B1AvatarPlan::userId($id);
        $result = ['verdict' => 'FAIL_DEV_ADMIN_AVATAR_QA', 'phase' => 'sql_precheck',
            'target_user_id' => $id,
            'mode' => $apply ? 'EXPLICIT_DELEGATED_AVATAR_WRITE' : 'READ_ONLY_PREFLIGHT',
            'admin_is_distinct' => false, 'owner_verified' => false,
            'sql_verified' => false, 'gd_all_codecs' => false,
            'admin_probe' => 0, 'owner_denied' => 0,
            'upload_http' => null, 'replace_http' => null, 'delete_http' => null,
            'static_first' => null, 'static_second' => null, 'old_gone' => null,
            'final_sql_empty' => false, 'physical_cleaned' => false,
            'last_http' => null, 'image_urls' => [],
            'real_http_concurrency' => 'NOT_TESTED',
            'forced_sql_storage_failure' => 'NOT_TESTED',
            'independent_baseline_parity' => 'NOT_TESTED'];
        try {
            M63B2AvatarAudit::target($id, null);
            $result['sql_verified'] = true;
            $result['phase'] = 'login_two_accounts';
            $admin = new M63B1AvatarTransport($id);
            $adminMe = $admin->login($adminUsername, $adminPassword);
            $this->trace($result, 'admin_me', $adminMe);
            $owner = new M63B1AvatarTransport($id);
            $ownerMe = $owner->login($targetUsername, $targetPassword);
            $this->trace($result, 'owner_me', $ownerMe);
            $actors = M63B2AvatarPlan::actors($adminMe, $ownerMe, $id);
            $adminId = $actors['admin_id'];
            $result['admin_is_distinct'] = true;
            $result['owner_verified'] = true;
            $result['phase'] = 'codec_check';
            $result['gd_all_codecs'] = M63B1AvatarPlan::codecsReady();
            if (!$result['gd_all_codecs']) throw new RuntimeException('Required GD codecs unavailable.');
            M63B2AvatarAudit::target($id, null);
            if (!$apply) {
                $result['phase'] = 'complete';
                $result['verdict'] = 'PASS_DEV_ADMIN_AVATAR_PREFLIGHT';
                return $result;
            }

            // A missing multipart image can NEVER mutate a file or metadata. This
            // probes target-scoped PBAC before sending any valid image bytes.
            $result['phase'] = 'delegated_pbac_probe';
            $messages = [];
            foreach (['en', 'vi'] as $locale) {
                $probe = $admin->multipart(null, 'image/png', $locale);
                $this->trace($result, 'delegated_probe_'.$locale, $probe);
                $messages[$locale] = M63B2AvatarPlan::allowedProbe($probe, $locale);
                $result['admin_probe']++;
            }
            if ($messages['en'] === $messages['vi']) {
                throw new RuntimeException('Delegated validation messages must be localized.');
            }
            M63B2AvatarAudit::target($id, null);
            M63B1AvatarPlan::ownEmptyActor($owner->me(), $id);

            // The unprivileged target must not be able to change the admin's
            // Avatar. Use an EMPTY multipart (never valid image bytes) even
            // if the protected admin has an existing Avatar.
            $result['phase'] = 'nonowner_denial';
            $deniedClient = new M63B1AvatarTransport($adminId);
            $deniedClient->login($targetUsername, $targetPassword);
            foreach (['en', 'vi'] as $locale) {
                $denied = $deniedClient->multipart(null, 'image/png', $locale);
                $this->trace($result, 'nonowner_denied_'.$locale, $denied);
                M63B2AvatarPlan::deniedProbe($denied, $locale);
                $result['owner_denied']++;
            }
            M63B2AvatarPlan::unchangedAdminAvatar(
                $actors['admin'], $admin->me()['json']['data']['user'] ?? []);
            M63B2AvatarAudit::target($id, null);

            $a = M63B1AvatarPlan::sample(1);
            $b = M63B1AvatarPlan::sample(2);
            $result['phase'] = 'delegated_upload_first';
            M63B2AvatarAudit::target($id, null);
            $upload = $admin->multipart($a['bytes']);
            $this->trace($result, 'upload', $upload);
            $result['upload_http'] = $upload['status'];
            $urlA = M63B1AvatarPlan::responseUrl($upload, $id);
            $result['image_urls']['first'] = $urlA;
            M63B1AvatarPlan::ownAvatar($owner->me(), $id, $urlA);
            M63B2AvatarAudit::target($id, M63B1AvatarPlan::filename($urlA));
            $result['static_first'] = $this->staticFile($admin, $urlA);

            $result['phase'] = 'delegated_replace';
            M63B2AvatarAudit::target($id, M63B1AvatarPlan::filename($urlA));
            $replace = $admin->multipart($b['bytes']);
            $this->trace($result, 'replace', $replace);
            $result['replace_http'] = $replace['status'];
            $urlB = M63B1AvatarPlan::responseUrl($replace, $id);
            if ($urlB === $urlA) throw new RuntimeException('Avatar replacement reused filename.');
            $result['image_urls']['second'] = $urlB;
            M63B1AvatarPlan::ownAvatar($owner->me(), $id, $urlB);
            M63B2AvatarAudit::target($id, M63B1AvatarPlan::filename($urlB));
            $result['static_second'] = $this->staticFile($admin, $urlB);
            if (is_file(M63B1AvatarPlan::physicalPath($urlA))) {
                throw new RuntimeException('Old delegated Avatar remains on disk.');
            }
            $result['old_gone'] = $admin->staticImage($urlA)['status'];
            if ($result['old_gone'] !== 404) throw new RuntimeException('Old Avatar still served.');

            $result['phase'] = 'delegated_delete';
            M63B2AvatarAudit::target($id, M63B1AvatarPlan::filename($urlB));
            $delete = $admin->delete();
            $this->trace($result, 'delete', $delete);
            $result['delete_http'] = $delete['status'];
            M63B1AvatarPlan::deleted($delete, $id);
            $end = M63B1AvatarPlan::ownEmptyActor($owner->me(), $id);
            M63B2AvatarPlan::unchangedProfile($actors['owner'], $end);
            M63B2AvatarAudit::target($id, null);
            $result['final_sql_empty'] = true;
            if (is_file(M63B1AvatarPlan::physicalPath($urlB))
                || $admin->staticImage($urlB)['status'] !== 404) {
                throw new RuntimeException('Deleted delegated Avatar persists.');
            }
            $result['physical_cleaned'] = true;
            M63B2AvatarPlan::unchangedAdminAvatar(
                $actors['admin'], $admin->me()['json']['data']['user'] ?? []);
            $result['phase'] = 'complete';
            $result['verdict'] = 'PASS_DEV_ADMIN_AVATAR_HTTP_FS_SQL';
        } catch (Throwable $error) {
            $result['failure_class'] = get_class($error);
        }
        return $result;
    }

    private function staticFile(M63B1AvatarTransport $http, string $url): int
    {
        $path = M63B1AvatarPlan::physicalPath($url);
        $bytes = @file_get_contents($path);
        if (!is_string($bytes) || strlen($bytes) < 32 || strlen($bytes) > 2*1024*1024
            || (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes) !== 'image/png') {
            throw new RuntimeException('Avatar physical image missing or invalid.');
        }
        $static = $http->staticImage($url);
        if ($static['status'] !== 200 || strtolower(trim(explode(';', $static['content_type'], 2)[0])) !== 'image/png'
            || hash('sha256', $static['bytes']) !== hash('sha256', $bytes)) {
            throw new RuntimeException('Apache static Avatar differs from physical public/upload bytes.');
        }
        return 200;
    }

    /** @param array<string,mixed> $result @param array<string,mixed> $response */
    private function trace(array &$result, string $phase, array $response): void
    {
        $code = $response['json']['error']['code'] ?? null;
        $result['last_http'] = ['phase' => $phase, 'status' => (int)($response['status'] ?? 0),
            'error_code' => is_string($code) && preg_match('/\A[A-Z][A-Z0-9_]{0,95}\z/D', $code) === 1
                ? $code : null];
    }
}
