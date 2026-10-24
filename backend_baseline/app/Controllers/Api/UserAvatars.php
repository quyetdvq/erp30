<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Exceptions\DomainException;
use App\Services\UserAvatarService;
use App\Services\UserAvatarStorage;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;

/** Authorized image upload/delete only. Images are served as static files. */
final class UserAvatars extends ApiController
{
    public function upload(int $id): ResponseInterface
    {
        $service = $this->service();
        // Deny unauthorized request before explaining any multipart validation error.
        $service->assertCanMutate($id);
        if (! $this->request instanceof IncomingRequest) {
            throw new DomainException('USER_AVATAR_INVALID_CONTENT', ['field' => 'image']);
        }
        $file = $this->request->getFile('image');
        if ($file === null || ! $file->isValid()) {
            $reason = $file?->getError();
            $code = in_array($reason, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? 'USER_AVATAR_INVALID_SIZE' : 'USER_AVATAR_INVALID_CONTENT';
            throw new DomainException($code, ['field' => 'image']);
        }
        $size = $file->getSize();
        if (! is_int($size) || $size < 1 || $size > UserAvatarStorage::MAX_BYTES) {
            throw new DomainException('USER_AVATAR_INVALID_SIZE', ['field' => 'image']);
        }
        return $this->respondData($service->upload($id, $file->getTempName()));
    }

    public function delete(int $id): ResponseInterface
    {
        return $this->respondData($this->service()->remove($id));
    }

    private function service(): UserAvatarService
    {
        return new UserAvatarService();
    }
}
