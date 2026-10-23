<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Storage;

use App\Infrastructure\Media\YearlyImageStorage;

/** Identity adapter: all decoding, resizing and file naming belong to shared storage. */
final class UserAvatarStorage
{
    public const MAX_BYTES = YearlyImageStorage::AVATAR_MAX_BYTES;
    public const MAX_DIMENSION = YearlyImageStorage::MAX_DIMENSION;

    private YearlyImageStorage $files;

    /** Optional uploads root is used only by filesystem tests, never HTTP input. */
    public function __construct(?string $directory = null)
    {
        $this->files = new YearlyImageStorage($directory);
    }

    /** @return array{mime:string,extension:string,size:int,width:int,height:int} */
    public function inspect(string $temporaryFile): array
    {
        return $this->files->inspect($temporaryFile, 'avatar');
    }

    /** @return array{filename:string,mime:string,size:int} */
    public function store(string $temporaryFile): array
    {
        return $this->files->store($temporaryFile, 'avatar');
    }

    public function path(string $filename): string
    {
        return $this->files->path($filename, 'avatar');
    }

    public function delete(string $filename): void
    {
        $this->files->delete($filename, 'avatar');
    }
}
