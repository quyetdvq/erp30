<?php

declare(strict_types=1);

namespace App\Commands;

use App\Infrastructure\Media\YearlyImageStorage;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/** One-time, opt-in migration for pre-YYYY Product/Avatar filenames. Original files are retained. */
final class MigrateLegacyImages extends BaseCommand
{
    protected $group = 'DinkERP';
    protected $name = 'images:migrate';
    protected $description = 'Preview or migrate old Product/Avatar images into year-based uploads.';
    protected $usage = 'images:migrate [--apply --backup-confirmed --database=<expected database>]';
    protected $options = [
        'apply' => 'Perform writes after backup and DB target are confirmed.',
        'backup-confirmed' => 'Confirm that a verified database/filesystem backup exists.',
        'database:' => 'Required exact database name for write mode.',
    ];

    public function run(array $params): void
    {
        if ($params !== []) {
            throw new RuntimeException('Unsupported arguments; use --apply --backup-confirmed --database=...');
        }
        $db = db_connect();
        if (! $db instanceof BaseConnection) {
            throw new RuntimeException('Database connection required.');
        }
        $apply = (bool) CLI::getOption('apply');
        $dbName = (string) $db->getDatabase();
        CLI::write('Target database: ' . $dbName . ' | mode: ' . ($apply ? 'APPLY' : 'DRY RUN'));
        if ($apply && (! CLI::getOption('backup-confirmed')
            || ! hash_equals($dbName, (string) (CLI::getOption('database') ?? '')))) {
            throw new RuntimeException('STOP: back up DB + uploads and specify the exact --database name.');
        }

        $files = new YearlyImageStorage();
        foreach ([
            ['products', 'id', 'image_filename', 'product'],
            [config('Auth')->tables['users'], 'id', 'avatar_filename', 'avatar'],
        ] as [$table, $id, $column, $type]) {
            $rows = $db->table($table)->select("{$id}, {$column}")
                ->where($column . ' IS NOT NULL', null, false)->get()->getResultArray();
            $legacy = 0;
            $migrated = 0;
            foreach ($rows as $row) {
                $filename = $row[$column] ?? null;
                if (! is_string($filename) || $filename === ''
                    || YearlyImageStorage::publicUrl($filename, $type) !== null) {
                    continue;
                }
                $legacy++;
                try {
                    $path = $files->path($filename, $type);
                    if (! is_file($path) || is_link($path)) {
                        throw new RuntimeException('Legacy image missing.');
                    }
                    if (! $apply) {
                        continue;
                    }
                    $new = $files->store($path, $type);
                    try {
                        $values = [$column => $new['filename']];
                        if ($type === 'product') {
                            $values['image_mime'] = null;
                            $values['image_size_bytes'] = null;
                        }
                        $updated = $db->table($table)->where($id, $row[$id])
                            ->where($column, $filename)->update($values);
                        if ($updated === false || $db->affectedRows() !== 1) {
                            throw new RuntimeException('Legacy image metadata was changed concurrently.');
                        }
                        $migrated++;
                    } catch (\Throwable $error) {
                        $files->delete($new['filename'], $type);
                        throw $error;
                    }
                } catch (\Throwable $error) {
                    CLI::write("SKIP {$type} #{$row[$id]}: {$error->getMessage()}", 'yellow');
                }
            }
            CLI::write("{$type}: {$legacy} legacy records; {$migrated} migrated.");
        }
        CLI::write($apply
            ? 'Migration complete. Originals retained for explicit post-verification cleanup.'
            : 'No database or filesystem writes.');
    }
}
