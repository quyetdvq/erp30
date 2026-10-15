<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Exceptions\DomainException;
use App\Services\UserAvatarService;
use App\Services\UserAvatarStorage;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;

/** Authenticated binary User avatar resource; files never pass through public/. */
final class UserAvatars extends ApiController
{
    public function show(int $id): ResponseInterface
    {
        $image = $this->service()->read($id);
        $body = @file_get_contents($image['path']);
        if (! is_string($body)) {
            throw new DomainException('USER_AVATAR_NOT_FOUND');
        }
        return $this->response
            ->setHeader('Content-Type', $image['mime'])
            ->setHeader('Content-Disposition', 'inline')
            ->setHeader('Cache-Control', 'private, no-store')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody($body);
    }

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
