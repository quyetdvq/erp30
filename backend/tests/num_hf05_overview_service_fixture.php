<?php

declare(strict_types=1);

/** CI4-free, read-only fake database + UnifiedAccessService fixture. */
namespace CodeIgniter\Database {
    class BaseConnection {}
}

namespace App\Modules\Access {
    class UnifiedAccessService
    {
        public function canView(string $type, array $row): bool
        {
            return $type === 'resource' && ($row['view'] ?? false) === true;
        }
        public function decideMutation(string $type, string $action, array $row): array
        {
            if ($type !== 'resource' || $action !== 'configure_numbering') {
                throw new \RuntimeException('Wrong PBAC predicate: '.$type.'.'.$action);
            }
            return ['allowed'=>($row['configure'] ?? false) === true];
        }
    }
}

namespace {
    require_once __DIR__.'/../app/Exceptions/DomainException.php';
    require_once __DIR__.'/../app/Modules/Numbering/Configuration/NumberingRules.php';
    require_once __DIR__.'/../app/Modules/Numbering/Configuration/NumberingOverview.php';
    require_once __DIR__.'/../app/Modules/Numbering/Configuration/NumberingService.php';

    use App\Modules\Access\UnifiedAccessService;
    use App\Modules\Numbering\Configuration\NumberingService;
    use CodeIgniter\Database\BaseConnection;

    final class FakeResult
    {
        public function __construct(private array $rows) {}
        public function getResultArray(): array { return $this->rows; }
    }
    final class FakeBuilder
    {
        public array $ids = [];
        public function __construct(private FakeConnection $conn, private string $table) {}
        public function orderBy(string $field, string $order): self { return $this; }
        public function whereIn(string $field, array $ids): self
        {
            if ($field !== 'resource_id' || $this->table !== 'auto_numbering_series') {
                throw new \RuntimeException('Unexpected unrestricted whereIn');
            }
            $this->ids = $ids;
            return $this;
        }
        public function get(): FakeResult
        {
            $this->conn->calls[] = [$this->table, $this->ids];
            $rows = $this->conn->tables[$this->table];
            if ($this->table === 'resources') return new FakeResult($rows);
            return new FakeResult(array_values(array_filter($rows, fn (array $row): bool =>
                in_array((string) $row['resource_id'], $this->ids, true))));
        }
    }
    final class FakeConnection extends BaseConnection
    {
        public array $calls = [];
        public function __construct(public array $tables) {}
        public function table(string $name): FakeBuilder { return new FakeBuilder($this, $name); }
    }

    $resources = [
        ['id'=>'3','type'=>'ORDER','name'=>'Orders','domain'=>'MASTER','status'=>'ACTIVE','view'=>true,'configure'=>true],
        ['id'=>'2','type'=>'PRODUCT','name'=>'Products','domain'=>'MASTER','status'=>'ACTIVE','view'=>true,'configure'=>true],
        ['id'=>'4','type'=>'SECRET','name'=>'Hidden','domain'=>'MASTER','status'=>'ACTIVE','view'=>false,'configure'=>true],
        ['id'=>'5','type'=>'DRAFT','name'=>'No configure','domain'=>'MASTER','status'=>'ACTIVE','view'=>true,'configure'=>false],
    ];
    $data = [
        ['id'=>'20','resource_id'=>'2','name'=>'Product','start_number'=>1,'is_default'=>1],
        ['id'=>'30','resource_id'=>'3','name'=>'Order','start_number'=>1,'is_default'=>0],
        ['id'=>'40','resource_id'=>'4','name'=>'Secret','start_number'=>1,'is_default'=>1],
    ];
    $make = static function (array $resourceRows, array $seriesRows): array {
        $db = new FakeConnection(['resources'=>$resourceRows, 'auto_numbering_series'=>$seriesRows]);
        $service = (new \ReflectionClass(NumberingService::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(NumberingService::class, 'db'))->setValue($service, $db);
        (new \ReflectionProperty(NumberingService::class, 'access'))->setValue($service, new UnifiedAccessService());
        (new \ReflectionProperty(NumberingService::class, 'actorId'))->setValue($service, '1');
        return [$service, $db];
    };
    [$service, $db] = $make($resources, $data);
    $overview = $service->overview();
    $assert = static function (bool $pass, string $name): void {
        if (!$pass) throw new \RuntimeException('FAIL: '.$name);
        echo 'PASS: '.$name.PHP_EOL;
    };
    $assert($db->calls === [['resources', []], ['auto_numbering_series', ['3','2']]],
        'Exactly two DB SELECTs for all authorized series, no per-Resource select');
    $assert(array_column($overview['series'], 'id') === ['30','20'], 'Unauthorized series not returned');
    $assert(array_column($overview['resources'], 'id') === ['3','2'], 'Authorized Resource selectors retained');
    [$service, $db] = $make(array_map(static fn (array $row): array => [...$row,'configure'=>false], $resources), $data);
    $assert($service->overview() === ['resources'=>[],'series'=>[]], 'Empty PBAC result is an empty overview');
    $assert($db->calls === [['resources', []]], 'No numbering series SQL query without authorization');
    echo 'TOTAL: 5 Numbering overview service assertions passed.'.PHP_EOL;
}
