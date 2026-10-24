<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;
use RuntimeException;

/** Deployment limits for the Product-only import surface. */
final class Import extends BaseConfig
{
    public const DEFAULT_MAX_FILE_BYTES = 10 * 1024 * 1024;
    public const DEFAULT_MAX_ROWS = 5000;

    public int $maxFileBytes = self::DEFAULT_MAX_FILE_BYTES;

    public int $maxRows = self::DEFAULT_MAX_ROWS;

    /** @var list<string> */
    public array $allowedFormats = ['CSV', 'XLSX'];

    public function __construct()
    {
        parent::__construct();

        $this->maxFileBytes = $this->positiveSetting('import.max_file_bytes', self::DEFAULT_MAX_FILE_BYTES);
        $this->maxRows = $this->positiveSetting('import.max_rows', self::DEFAULT_MAX_ROWS);
    }

    public function assertPhpUploadSettings(): void
    {
        $uploadLimit = $this->parseIniBytes((string) ini_get('upload_max_filesize'));
        $postLimit = $this->parseIniBytes((string) ini_get('post_max_size'));

        if ($uploadLimit < $this->maxFileBytes || $postLimit < $this->maxFileBytes) {
            throw new RuntimeException('PHP upload settings are below the configured Product import file limit.');
        }
    }

    public function assertFileSize(int $bytes): void
    {
        if ($bytes < 0 || $bytes > $this->maxFileBytes) {
            throw new \App\Exceptions\DomainException(
                'IMPORT_FILE_TOO_LARGE',
                ['field' => 'file', 'max_bytes' => $this->maxFileBytes],
            );
        }
    }

    public function assertRowCount(int $rows): void
    {
        if ($rows < 0 || $rows > $this->maxRows) {
            throw new \App\Exceptions\DomainException(
                'IMPORT_ROW_LIMIT_EXCEEDED',
                ['field' => 'file', 'max_rows' => $this->maxRows],
            );
        }
    }

    private function parseIniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || ! preg_match('/\A([0-9]+)([KMG])?\z/i', $value, $matches)) {
            return 0;
        }

        $bytes = (int) $matches[1];
        $multiplier = match (strtoupper($matches[2] ?? '')) {
            'K' => 1024,
            'M' => 1024 ** 2,
            'G' => 1024 ** 3,
            default => 1,
        };

        return $bytes * $multiplier;
    }

    private function positiveSetting(string $key, int $default): int
    {
        $value = env($key, $default);
        if (! is_numeric($value) || (int) $value < 1) {
            throw new RuntimeException("{$key} must be a positive integer.");
        }

        return (int) $value;
    }
}
