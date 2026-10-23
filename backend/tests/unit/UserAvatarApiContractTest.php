<?php

declare(strict_types=1);

use App\Exceptions\DomainException;
use App\Infrastructure\Api\ApiErrorMapper;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal Structural HTTP contract and stable error/status mapping. */
final class UserAvatarApiContractTest extends CIUnitTestCase
{
    public function testOnlyUploadDeleteRoutesAreRegisteredAndUserCreationIsUnchanged(): void
    {
        $routes = (string) file_get_contents(__DIR__ . '/../../app/Config/Routes.php');
        foreach (['put' => 'upload', 'delete' => 'delete'] as $verb => $action) {
            $this->assertStringContainsString(
                "\$routes->{$verb}('users/(:num)/avatar', 'Api\\\\UserAvatars::{$action}/\$1');",
                $routes,
            );
        }
        $this->assertStringNotContainsString("\$routes->get('users/(:num)/avatar'", $routes);
        $this->assertStringContainsString("\$routes->post('users', 'Api\\\\AuthorizationWorkflow::createUser');", $routes);
    }

    public function testAvatarErrorMessagesExistInVietnameseAndEnglish(): void
    {
        foreach (['vi', 'en'] as $locale) {
            $messages = require __DIR__ . '/../../app/Language/' . $locale . '/Api.php';
            foreach ([
                'userAvatarInvalidSize', 'userAvatarInvalidType', 'userAvatarInvalidContent',
                'userAvatarNotFound', 'userAvatarConcurrentUpdate', 'userAvatarStorageFailed',
            ] as $key) {
                $this->assertArrayHasKey($key, $messages);
                $this->assertNotSame('', trim((string) $messages[$key]));
            }
        }
    }

    public function testAvatarErrorsUseCanonicalHttpStatusWithoutServerPaths(): void
    {
        foreach ([
            ['USER_AVATAR_INVALID_SIZE', 422],
            ['USER_AVATAR_INVALID_TYPE', 422],
            ['USER_AVATAR_INVALID_CONTENT', 422],
            ['USER_AVATAR_NOT_FOUND', 404],
            ['USER_AVATAR_CONCURRENT_UPDATE', 409],
            ['USER_AVATAR_STORAGE_FAILED', 500],
        ] as [$code, $status]) {
            $error = ApiErrorMapper::map(new DomainException($code, [], new RuntimeException('/secret/private/dir')));
            $this->assertSame($status, $error['status']);
            $this->assertSame($code, $error['code']);
            $this->assertStringNotContainsString('/secret', $error['message']);
        }
    }
}
