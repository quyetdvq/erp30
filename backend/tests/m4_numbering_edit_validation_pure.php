<?php

declare(strict_types=1);

/**
 * Offline Numbering validate-config integration fixture with an in-memory CI4
 * DB stand-in. This never opens a database, writes data, or consumes counters.
 */
namespace CodeIgniter\Database {
    class BaseConnection {}
}

namespace App\Modules\Access {
    class UnifiedAccessService
    {
        public function __construct(private bool $allowed = true) {}
        public function decideMutation(string $type, string $action, array $resource): array
        {
            return ['allowed' => $this->allowed];
        }
    }
}

namespace {
    require_once __DIR__.'/../app/Exceptions/DomainException.php';
    require_once __DIR__.'/../app/Modules/Numbering/Configuration/NumberingRules.php';
    require_once __DIR__.'/../app/Modules/Numbering/Configuration/NumberingService.php';

    use App\Exceptions\DomainException;
    use App\Modules\Access\UnifiedAccessService;
    use App\Modules\Numbering\Configuration\NumberingService;
    use CodeIgniter\Database\BaseConnection;

    final class FakeResult
    {
        public function __construct(private array $rows) {}
        public function getRowArray(): ?array { return $this->rows[0] ?? null; }
        public function getResultArray(): array { return $this->rows; }
    }

    final class FakeBuilder
    {
        private array $conditions = [];
        public function __construct(private array $rows) {}
        public function where(string $column, mixed $value): self
        {
            $this->conditions[$column] = (string) $value;
            return $this;
        }
        public function select(string $column): self { return $this; }
        public function limit(int $limit): self { return $this; }
        public function get(): FakeResult
        {
            return new FakeResult(array_values(array_filter($this->rows, function (array $row): bool {
                foreach ($this->conditions as $column => $value) {
                    if ((string) ($row[$column] ?? '') !== $value) return false;
                }
                return true;
            })));
        }
    }

    final class FakeConnection extends BaseConnection
    {
        public int $readCount = 0;
        public function __construct(private array $tables) {}
        public function table(string $name): FakeBuilder
        {
            $this->readCount++;
            if (!array_key_exists($name, $this->tables)) {
                throw new \RuntimeException('Unexpected table: '.$name);
            }
            return new FakeBuilder($this->tables[$name]);
        }
    }

    $self = [
        'id'=>'42', 'resource_id'=>'7', 'series_code'=>'INV', 'name'=>'Invoice',
        'scope_type'=>'GROUP', 'pattern'=>'INV-{SEQ:6}', 'reset_policy'=>'NEVER',
        'generation_mode'=>'AUTO_IF_EMPTY', 'start_number'=>'1', 'increment_by'=>1,
        'is_default'=>1, 'status'=>'ACTIVE',
    ];
    $other = [
        'id'=>'43', 'resource_id'=>'7', 'series_code'=>'ORD', 'name'=>'Order',
        'scope_type'=>'GROUP', 'pattern'=>'ORD-{SEQ:5}', 'reset_policy'=>'NEVER',
        'generation_mode'=>'AUTO_IF_EMPTY', 'start_number'=>'1', 'increment_by'=>1,
        'is_default'=>0, 'status'=>'ACTIVE',
    ];
    $crossResource = [...$self, 'id'=>'99', 'resource_id'=>'8', 'series_code'=>'OTHER'];
    $rows = [
        'resources'=>[['id'=>'7', 'type'=>'product'], ['id'=>'8', 'type'=>'order']],
        'auto_numbering_series'=>[$self, $other, $crossResource],
        'auto_numbering_counters'=>[],
    ];
    $payload = static function (array $row): array {
        unset($row['id'], $row['resource_id']);
        return $row;
    };
    $make = static function (array $tables, bool $allowed = true): NumberingService {
        $service = (new \ReflectionClass(NumberingService::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(NumberingService::class, 'db'))->setValue($service, new FakeConnection($tables));
        (new \ReflectionProperty(NumberingService::class, 'access'))->setValue($service, new UnifiedAccessService($allowed));
        (new \ReflectionProperty(NumberingService::class, 'actorId'))->setValue($service, '1');
        return $service;
    };
    $count = 0;
    $check = static function (bool $ok, string $name) use (&$count): void {
        if (!$ok) throw new \RuntimeException('FAIL: '.$name);
        $count++;
        echo 'PASS: '.$name.PHP_EOL;
    };
    $reject = static function (callable $call, string $code, string $name) use ($check): void {
        try { $call(); }
        catch (DomainException $exception) {
            $check($exception->getErrorCode() === $code, $name.' ('.$code.')');
            return;
        }
        throw new \RuntimeException('Expected '.$code.': '.$name);
    };

    $service = $make($rows);
    $valid = $service->validateConfig('7', [...$payload($self), 'series_id'=>'42']);
    $check($valid['valid'] === true && $valid['normalized_pattern'] === 'INV-{SEQ:6}',
        'Edit preview excludes only its own series');
    $reject(static fn () => $service->validateConfig('7', $payload($self)),
        'NUM_SERIES_DUPLICATE', 'Create preview still detects existing series');
    $reject(static fn () => $service->validateConfig('7', [...$payload($self), 'series_id'=>'43']),
        'NUM_SERIES_DUPLICATE', 'Edit preview cannot exclude a different series');
    $reject(static fn () => $service->validateConfig('7', [...$payload($self), 'series_id'=>'99']),
        'NUM_SERIES_NOT_FOUND', 'Cross-Resource edit context rejected');
    $reject(static fn () => $service->validateConfig('7', [...$payload($self), 'series_id'=>'404']),
        'NUM_SERIES_NOT_FOUND', 'Unknown edit context rejected');
    $reject(static fn () => $service->validateConfig('7', [...$payload($self), 'series_id'=>'0']),
        'NUM_INVALID_CONFIGURATION', 'Invalid edit ID rejected');
    $reject(static fn () => $service->validateConfig('7', [...$payload($self), 'series_id'=>null]),
        'NUM_INVALID_CONFIGURATION', 'Explicit null edit ID rejected');
    $reject(static fn () => $service->validateConfig('7', [...$payload($self), 'series_id'=>'42', 'pattern'=>'ORD-{SEQ:9}']),
        'NUM_PATTERN_COLLISION', 'Edit detects collision with OTHER active pattern despite SEQ width');
    $reject(static fn () => $service->validateConfig('7', [...$payload($self), 'series_id'=>'42', 'series_code'=>'ORD']),
        'NUM_SERIES_DUPLICATE', 'Edit detects duplicate OTHER code');
    $otherDefault = $rows;
    $otherDefault['auto_numbering_series'][1]['is_default'] = 1;
    $reject(static fn () => $make($otherDefault)->validateConfig('7', [...$payload($self), 'series_id'=>'42']),
        'NUM_ACTIVE_DEFAULT_CONFLICT', 'Edit rejects conflict with other active default');
    $issued = $rows;
    $issued['auto_numbering_counters'] = [['id'=>'501', 'numbering_series_id'=>'42']];
    $check($make($issued)->validateConfig('7', [...$payload($self), 'series_id'=>'42'])['valid'],
        'Issued series allows unchanged immutable fields');
    $reject(static fn () => $make($issued)->validateConfig('7', [...$payload($self), 'series_id'=>'42', 'series_code'=>'INVC']),
        'NUM_ISSUED_FIELD_IMMUTABLE', 'Edit preview shares PATCH issuance guard');
    $check($service->validateConfig('7', [...$payload($self), 'series_code'=>'NEW', 'pattern'=>'NEW-{SEQ:4}', 'is_default'=>0])['valid'],
        'New non-colliding Create preview succeeds without edit context');
    $reject(static fn () => $make($rows, false)->validateConfig('7', [...$payload($self), 'series_id'=>'42']),
        'NUM_AUTHORIZATION_DENIED', 'Existing PBAC denial preserved');
    $check(true, 'FakeConnection exposes read methods only: preview never writes');
    echo 'TOTAL: '.$count.' numbering preview/edit assertions passed.'.PHP_EOL;
}
