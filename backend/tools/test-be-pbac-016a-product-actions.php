<?php

declare(strict_types=1);

/**
 * BE-PBAC-016A: authoritative Product action hints, vendor-free characterization.
 * Uses the real Bootstrap adapter and Product list contracts with recorded PBAC
 * and trusted State doubles. No CI4/Shield/DB connection, SQL or writes.
 */
namespace CodeIgniter\Database {
    interface ConnectionInterface {}
}
namespace App\Repositories {
    final class ResourceCatalogRepository
    {
        public static ?string $domain = 'MASTER';
        public function __construct(mixed $db) {}
        public function findResourceByType(string $type): ?array
        {
            return self::$domain === null ? null : ['type' => $type, 'domain' => self::$domain];
        }
    }
}
namespace App\Services {
    use App\Exceptions\DomainException;

    final class ResourceWorkflowStateResolver
    {
        public function __construct(mixed $db) {}
        public function persistedStateId(string $resource, array $row): ?int
        {
            return match ($row['status'] ?? null) {
                'ACTIVE' => 101,
                'DRAFT' => 202,
                'INACTIVE' => 303,
                default => throw new DomainException('WF_AUTHORIZATION_STATE_NOT_MAPPED'),
            };
        }
    }
}
namespace App\Authorization {
    use App\Infrastructure\Mutation\OperationContext;

    final class Be016aDecision
    {
        public function __construct(private bool $allowed) {}
        public function isAllowed(): bool { return $this->allowed; }
    }
    final class AuthorizationService
    {
        /** @var list<array{action:string, status:string, id:int, state:?int, context:OperationContext}> */
        public array $observed = [];
        public bool $fail = false;
        public function authorize(
            OperationContext $operation, string $resource, string $action,
            EvaluationOrganizationRequest $context, mixed $stateId = null,
        ): Be016aDecision {
            if ($this->fail) { throw new \RuntimeException('PBAC read failed'); }
            $row = $context->persistedTarget;
            if ($resource !== 'product' || $row === null) {
                throw new \LogicException('Actions must use an authoritative persisted Product');
            }
            $status = (string) $row['status'];
            if ($action === 'setstate' && $stateId !== ['ACTIVE' => 101, 'DRAFT' => 202, 'INACTIVE' => 303][$status]) {
                throw new \LogicException('setstate did not use trusted current FROM-State');
            }
            if ($action === 'edit' && $stateId !== null) {
                throw new \LogicException('edit unexpectedly used a caller State override');
            }
            $this->observed[] = [
                'action' => $action, 'status' => $status, 'id' => (int) $row['id'],
                'state' => $stateId, 'context' => $operation,
            ];
            // Deliberately make edit/setstate independent and the result
            // dependent on exact State, as PBAC does in production.
            return new Be016aDecision(match ($action) {
                'edit' => $status === 'DRAFT' && ((int) $row['id'] % 2 === 0),
                'setstate' => $status === 'ACTIVE' && ((int) $row['id'] % 2 === 1),
                default => false,
            });
        }
    }
}
namespace {
    use App\Authorization\AuthorizationService;
    use App\Bootstrap\ProductListAuthorizationAdapter;
    use App\Exceptions\DomainException;
    use App\Infrastructure\Mutation\OperationContext;
    use App\Infrastructure\Time\EvaluationTime;
    use App\Repositories\ResourceCatalogRepository;

    $app = dirname(__DIR__) . '/app/';
    spl_autoload_register(static function (string $class) use ($app): void {
        if (! str_starts_with($class, 'App\\')) { return; }
        $file = $app . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) { require $file; }
    });

    function p016aAssert(bool $condition, string $description): void
    {
        if (! $condition) { throw new \RuntimeException('BE-PBAC-016A ' . $description); }
    }
    function p016aCheck(string $label, callable $test, int &$passed): void
    {
        $test(); $passed++;
        echo "  {$label}: PASS\n";
    }
    function p016aAdapter(AuthorizationService $authorization, OperationContext $operation): ProductListAuthorizationAdapter
    {
        return new ProductListAuthorizationAdapter(
            new class implements \CodeIgniter\Database\ConnectionInterface {},
            static fn (): OperationContext => $operation,
            static fn (): AuthorizationService => $authorization,
            static fn (): bool => true,
        );
    }

    $time = new EvaluationTime(new \DateTimeImmutable('2026-10-22T09:00:00Z'));
    $operation = new OperationContext(77, $time, '016a', false);
    $rows = [
        ['id' => 1, 'status' => 'ACTIVE', 'product_code' => 'A'],
        ['id' => 2, 'status' => 'ACTIVE', 'product_code' => 'B'],
        ['id' => 4, 'status' => 'DRAFT', 'product_code' => 'C'],
        ['id' => 5, 'status' => 'DRAFT', 'product_code' => 'D'],
    ];
    $passed = 0;
    p016aCheck('MASTER action decisions share exact State and remain independent by action', static function () use ($operation, $rows): void {
        ResourceCatalogRepository::$domain = 'MASTER';
        $auth = new AuthorizationService();
        $shown = p016aAdapter($auth, $operation)->withAllowedActions($operation, $rows);
        p016aAssert(count($auth->observed) === 4, 'MASTER should evaluate 2 actions x 2 distinct States');
        p016aAssert(array_column($shown, 'id') === [1, 2, 4, 5], 'Row order changed');
        p016aAssert($shown[0]['allowed_actions'] === ['edit' => false, 'setstate' => true], 'ACTIVE actions wrong');
        p016aAssert($shown[1]['allowed_actions'] === $shown[0]['allowed_actions'], 'State reuse missing');
        p016aAssert($shown[2]['allowed_actions'] === ['edit' => true, 'setstate' => false], 'DRAFT actions wrong');
        p016aAssert($shown[3]['allowed_actions'] === $shown[2]['allowed_actions'], 'DRAFT reuse missing');
        foreach ($auth->observed as $decision) {
            p016aAssert($decision['context'] === $operation, 'Operation object was recaptured');
        }
        p016aAssert(! array_key_exists('delete', $shown[0]['allowed_actions']), 'Product has no delete action');
    }, $passed);
    p016aCheck('Unproven organizational scope never reuses another row decision', static function () use ($operation, $rows): void {
        ResourceCatalogRepository::$domain = 'ORGANIZATION';
        $auth = new AuthorizationService();
        $shown = p016aAdapter($auth, $operation)->withAllowedActions($operation, $rows);
        p016aAssert(count($auth->observed) === 8, 'Non-MASTER must evaluate each row independently');
        p016aAssert($shown[0]['allowed_actions']['setstate'] === true && $shown[1]['allowed_actions']['setstate'] === false,
            'Row-specific policy leaked between equal States');
        p016aAssert($shown[2]['allowed_actions']['edit'] === true && $shown[3]['allowed_actions']['edit'] === false,
            'Row-specific edit policy leaked between equal States');
        ResourceCatalogRepository::$domain = null;
        $auth = new AuthorizationService();
        p016aAdapter($auth, $operation)->withAllowedActions($operation, array_slice($rows, 0, 2));
        p016aAssert(count($auth->observed) === 4, 'Unknown metadata cannot enable group reuse');
        ResourceCatalogRepository::$domain = 'MASTER';
    }, $passed);
    p016aCheck('Permission decisions are never cached between list invocations', static function () use ($operation, $rows): void {
        $auth = new AuthorizationService();
        $adapter = p016aAdapter($auth, $operation);
        $adapter->withAllowedActions($operation, $rows);
        $adapter->withAllowedActions($operation, $rows);
        p016aAssert(count($auth->observed) === 8, 'Permissions escaped the invocation-local scope');
    }, $passed);
    p016aCheck('No action evaluation or metadata load for an empty page', static function () use ($operation): void {
        $auth = new AuthorizationService();
        p016aAssert(p016aAdapter($auth, $operation)->withAllowedActions($operation, []) === [], 'Empty page changed');
        p016aAssert($auth->observed === [], 'Empty page triggered authorization');
    }, $passed);
    p016aCheck('Missing or invalid persisted State fails closed', static function () use ($operation): void {
        $adapter = p016aAdapter(new AuthorizationService(), $operation);
        foreach ([['id' => 99], ['id' => 99, 'status' => 'unknown'], ['status' => 'ACTIVE']] as $row) {
            try { $adapter->withAllowedActions($operation, [$row]); }
            catch (DomainException) { continue; }
            throw new \RuntimeException('Malformed persisted Product accepted');
        }
    }, $passed);
    p016aCheck('Infrastructure PBAC failures propagate and do not create false action grants', static function () use ($operation, $rows): void {
        $auth = new AuthorizationService();
        $auth->fail = true;
        try { p016aAdapter($auth, $operation)->withAllowedActions($operation, $rows); }
        catch (\RuntimeException $exception) {
            p016aAssert($exception->getMessage() === 'PBAC read failed', 'PBAC failure suppressed');
            return;
        }
        throw new \RuntimeException('PBAC read failure was hidden');
    }, $passed);
    p016aCheck('List computes hints after authorized page and inside SQL snapshot', static function () use ($app): void {
        $text = (string) file_get_contents($app . 'Modules/Product/Product/Application/ProductListQueryService.php');
        p016aAssert(str_contains($text, 'return $authorized === null ? null : $this->withAllowedActions($authorized, $operation);'),
            'View-only batch not closed before mutation hints');
        p016aAssert(str_contains($text, 'if ($response[\'items\'] !== [])'), 'Empty pages should skip action checks');
        p016aAssert(substr_count($text, '$this->withAllowedActions(') >= 4, 'Some Product list paths omit hints');
        p016aAssert(strpos($text, 'authorizedStatuses($operation)') < strpos($text, 'recheckRowVisible($operation, $row)'),
            'State bucketing was moved after per-row visibility');
    }, $passed);
    p016aCheck('GET hints do not expand mutation readback or Product API action catalog', static function () use ($app): void {
        $facade = (string) file_get_contents($app . 'Services/Api050ProductCatalogService.php');
        $contract = (string) file_get_contents(dirname(dirname($app)) . '/contracts/openapi/openapi.yaml');
        p016aAssert(str_contains($facade, 'return $security->withAllowedActions($operation, [$row])[0];'),
            'Product detail GET missing hints');
        p016aAssert(str_contains($facade, 'return $this->getProductWithOperation($id, $operation);'),
            'Mutation readback was changed');
        p016aAssert(str_contains($contract, 'allowed_actions:') && ! preg_match('/product\.delete\b/', $contract),
            'Contract no longer records advisory Product actions');
    }, $passed);
    p016aCheck('Frontend hides denied and absent action flags instead of using base permission', static function (): void {
        $root = dirname(__DIR__, 2) . '/frontend/src/';
        $list = (string) file_get_contents($root . 'features/catalog/pages/ProductsView.vue');
        $editor = (string) file_get_contents($root . 'features/catalog/pages/ProductEditorView.vue');
        $modal = (string) file_get_contents($root . 'components/products/ProductDetailModal.vue');
        // Product may now render flat page rows and additional inline children
        // through one discriminated row wrapper. In both layouts, action hints
        // must still be checked on the actual Product, never on its parent.
        $plainRows = str_contains($list, 'row.allowed_actions?.edit === true')
            && str_contains($list, 'row.allowed_actions?.setstate === true');
        $inlineRows = str_contains($list, 'entry.product.allowed_actions?.edit === true')
            && str_contains($list, 'entry.product.allowed_actions?.setstate === true');
        p016aAssert($plainRows || $inlineRows, 'Product page does not use its own record action hints');
        p016aAssert(str_contains($editor, 'product.value?.allowed_actions?.edit === true')
            && str_contains($editor, 'product.value?.allowed_actions?.setstate === true'), 'Product editor ignored PBAC');
        p016aAssert(str_contains($modal, 'currentProduct.value?.allowed_actions?.edit === true'), 'Detail modal edit control not guarded');
    }, $passed);

    echo "BE-PBAC-016A PRODUCT ACTIONS SOURCE GATE PASS: {$passed} checks; real adapter and Product contracts, DB/Shield/HTTP NOT_RUN.\n";
}
