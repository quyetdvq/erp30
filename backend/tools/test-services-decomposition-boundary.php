<?php

declare(strict_types=1);

/**
 * BE-REORG-014B vendor-free behavior/ownership smoke gate.
 * No HTTP bootstrap, database, filesystem mutation or external packages.
 */
namespace CodeIgniter\HTTP\Exceptions {
    class BadRequestException extends \RuntimeException {}
}

namespace {
    use App\Exceptions\DomainException;
    use App\Infrastructure\Api\CommonListQuery;
    use App\Infrastructure\Api\CommonListQueryAdapter;
    use App\Infrastructure\Api\CommonListQueryAllowlist;
    use App\Modules\Authorization\Administration\Policies\Application\AuthorizedPolicyPageReader;
    use App\Modules\Authorization\Administration\Policies\Application\Ports\PolicyPageReadRepositoryPort;
    use App\Modules\Product\Import\ProductImportReadQueryPolicy;
    use App\Modules\Product\Import\ProductImportResponsePresenter;
    use App\Modules\Settings\Display\Application\DisplaySettingsInputNormalizer;
    use App\Modules\Settings\Display\Application\DisplaySettingsResponsePresenter;
    use App\Services\Api060ProductImportService;
    use App\Services\ApiDisplaySettingsService;
    use CodeIgniter\HTTP\Exceptions\BadRequestException;

    $app = dirname(__DIR__) . '/app/';
    spl_autoload_register(static function (string $class) use ($app): void {
        if (! str_starts_with($class, 'App\\')) {
            return;
        }
        $path = $app . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($path)) {
            require $path;
        }
    });

    /** @internal */
    function verify014b(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new \RuntimeException('014B regression: ' . $message);
        }
    }

    /** @internal */
    function same014b(mixed $expected, mixed $actual, string $message): void
    {
        verify014b($expected === $actual, $message . ': ' . var_export($actual, true));
    }

    /** @internal */
    function error014b(callable $call, string $class, ?string $code = null): void
    {
        try {
            $call();
        } catch (\Throwable $error) {
            verify014b($error instanceof $class, 'Wrong exception: ' . get_class($error));
            if ($code !== null) {
                verify014b($error instanceof DomainException, 'Expected DomainException to expose error code');
                same014b($code, $error->getErrorCode(), 'Domain error code');
            }
            return;
        }
        throw new \RuntimeException('014B regression: missing exception ' . $class);
    }

    // Display Settings: validations/errors must not depend on DB or API instantiation.
    $settings = new DisplaySettingsInputNormalizer();
    same014b('system', $settings->contextFromQuery(['context_type' => ' SYSTEM '])->value(), 'SYSTEM query context');
    same014b('organization:23', $settings->contextFromQuery(['context_type' => 'organization', 'organization_id' => '23'])->value(), 'organization query context');
    same014b('system', $settings->contextFromPayload(['type' => 'system'])->value(), 'SYSTEM payload context');
    same014b('organization:23', $settings->contextFromPayload(['type' => 'organization', 'organization_id' => 23])->value(), 'organization payload context');
    same014b(null, $settings->optionalOrganizationId(null), 'optional org null');
    same014b('23', $settings->optionalOrganizationId(23), 'optional org int normalization');
    error014b(fn () => $settings->contextFromQuery(['context_type' => 'COMPANY']), DomainException::class, 'DISPLAY_SETTING_CONTEXT_INVALID');
    error014b(fn () => $settings->contextFromQuery(['context_type' => 'SYSTEM', 'organization_id' => '23']), DomainException::class, 'DISPLAY_SETTING_CONTEXT_INVALID');
    error014b(fn () => $settings->contextFromPayload(['type' => 'organization', 'organization_id' => '0']), DomainException::class, 'DISPLAY_SETTING_CONTEXT_INVALID');
    error014b(fn () => $settings->optionalOrganizationId([]), DomainException::class, 'DISPLAY_SETTING_CONTEXT_INVALID');
    same014b(['Display.dateFormat' => 'YYYY-MM-DD'], $settings->changes([['key' => 'Display.dateFormat', 'value' => 'YYYY-MM-DD']]), 'valid changes');
    same014b(['Display.dateFormat'], $settings->keys(['Display.dateFormat']), 'valid reset keys');
    error014b(fn () => $settings->changes(null), DomainException::class, 'DISPLAY_SETTING_INVALID');
    error014b(fn () => $settings->changes([['key' => 'Display.dateFormat', 'value' => 'YYYY'], ['key' => 'Display.dateFormat', 'value' => 'YYYY-MM-DD']]), DomainException::class, 'DISPLAY_SETTING_DUPLICATE_KEY');
    error014b(fn () => $settings->changes([['key' => 'Display.unknown', 'value' => 'anything']]), DomainException::class, 'DISPLAY_SETTING_NOT_FOUND');
    error014b(fn () => $settings->changes([['key' => 'Display.quantityDecimals', 'value' => -1]]), DomainException::class, 'DISPLAY_SETTING_INVALID');
    error014b(fn () => $settings->keys(['Display.dateFormat', 'Display.dateFormat']), DomainException::class, 'DISPLAY_SETTING_DUPLICATE_KEY');
    error014b(fn () => $settings->keys(['Display.unknown']), DomainException::class, 'DISPLAY_SETTING_NOT_FOUND');

    $presentation = DisplaySettingsResponsePresenter::class;
    same014b('MONEY', $presentation::category('Display.money.symbolPosition'), 'money category');
    same014b('NUMBER', $presentation::category('Display.trailingZeros'), 'number category');
    same014b('DATE_TIME', $presentation::category('Display.firstDayOfWeek'), 'date category');
    same014b('GENERAL', $presentation::category('Display.unknown'), 'default category');
    $snap = [];
    foreach (\App\Infrastructure\Settings\DisplaySettingRegistry::definitions() as $key => $definition) {
        $snap[$key] = $definition['default'];
    }
    $projected = $presentation::runtimeSnapshot($snap);
    same014b($snap['Display.timezone'], $projected['timezone'], 'runtime timezone');
    same014b($snap['Display.money.decimalsMode'], $projected['money']['decimals_mode'], 'money shape');
    same014b(5, count($projected['number']), 'number API shape');

    // Keep the existing private façade methods and their callable signatures.
    $settingsApi = (new \ReflectionClass(ApiDisplaySettingsService::class))->newInstanceWithoutConstructor();
    $viaFacade = new \ReflectionMethod(ApiDisplaySettingsService::class, 'contextFromQuery');
    same014b('organization:23', $viaFacade->invoke($settingsApi, ['context_type' => 'ORGANIZATION', 'organization_id' => 23])->value(), 'Display Settings API delegate');
    same014b($projected, (new \ReflectionMethod(ApiDisplaySettingsService::class, 'runtimeSnapshot'))->invoke($settingsApi, $snap), 'Display Settings output delegate');

    // Import: date filters, entity scoping, case-sensitive SQL guard and output DTOs.
    $import = new ProductImportReadQueryPolicy();
    [$normalized, $dates] = $import->normalizeJobQuery(['filter' => [
        'created_from' => '2026-09-01', 'created_to' => ['2026-10-31'], 'mode' => 'UPSERT',
    ]]);
    same014b(['filter' => ['mode' => 'UPSERT']], $normalized, 'import filter stripping');
    same014b(['from' => '2026-09-01', 'to' => '2026-10-31'], $dates, 'import date window');
    error014b(fn () => $import->normalizeJobQuery(['filter' => 'broken']), BadRequestException::class);
    error014b(fn () => $import->normalizeJobQuery(['filter' => ['created_from' => '2026-10-31', 'created_to' => '2026-10-01']]), BadRequestException::class);
    error014b(fn () => $import->normalizeJobQuery(['filter' => ['created_from' => [1, 2]]]), BadRequestException::class);
    error014b(fn () => $import->validateFilterEnum(['filter' => ['mode' => 'broken']], 'mode', ['UPSERT']), BadRequestException::class);
    $import->validateFilterEnum(['filter' => ['mode' => ['upsert']]], 'mode', ['UPSERT']);
    verify014b($import->isVisibleJob(['entity_type' => 'product', 'created_at' => '2026-10-21 12:00:00'], $dates), 'visible product job');
    verify014b(! $import->isVisibleJob(['entity_type' => 'employee', 'created_at' => '2026-10-21'], $dates), 'non-product job filtered');
    verify014b(! $import->isVisibleJob(['entity_type' => 'PRODUCT', 'created_at' => '2026-11-01'], $dates), 'date window enforced');
    $importPresenter = new ProductImportResponsePresenter();
    same014b(['id' => '12', 'created_by' => '4', 'total_rows' => 2], $importPresenter->jobOutput(['id' => 12, 'created_by' => 4, 'total_rows' => '2']), 'import job projection');
    same014b('13', $importPresenter->rowOutput(['row_number' => '5', 'row_status' => 'SUCCESS', 'target_ref_id' => 13])['target_ref_id'], 'import row target ID projection');
    $queryParser = new CommonListQueryAdapter();
    $rowAllowlist = new CommonListQueryAllowlist(
        search: ['entity_key'], filters: ['row_status'], sort: ['row_number', 'row_status', 'entity_key', 'target_ref_id', 'created_at'],
    );
    $terminal = ['status' => 'COMPLETED'];
    $defaultRows = $queryParser->parse(['sort' => 'row_number'], $rowAllowlist);
    verify014b($import->canPageImportRowsInSql($terminal, $defaultRows), 'terminal SQL fast path');
    verify014b(! $import->canPageImportRowsInSql(['status' => 'PROCESSING'], $defaultRows), 'live rows use compatibility path');
    verify014b(! $import->canPageImportRowsInSql($terminal, $queryParser->parse(['sort' => 'entity_key'], $rowAllowlist)), 'noncanonical sort fallback');
    verify014b(! $import->canPageImportRowsInSql($terminal, $queryParser->parse(['q' => 'Hà Nội', 'sort' => 'row_number'], $rowAllowlist)), 'Unicode search fallback');
    verify014b(! $import->canPageImportRowsInSql($terminal, $queryParser->parse(['sort' => 'row_number', 'filter' => ['row_status' => 'success']], $rowAllowlist)), 'case sensitive status fallback');
    $importApi = (new \ReflectionClass(Api060ProductImportService::class))->newInstanceWithoutConstructor();
    $guard = new \ReflectionMethod(Api060ProductImportService::class, 'canPageImportRowsInSql');
    verify014b($guard->invoke($importApi, $terminal, $defaultRows), 'Reflection-compatible import guard');
    same014b($importPresenter->rowOutput(['row_number' => '5', 'row_status' => 'SUCCESS']), (new \ReflectionMethod(Api060ProductImportService::class, 'rowOutput'))->invoke($importApi, ['row_number' => '5', 'row_status' => 'SUCCESS']), 'import API output delegate');

    // Policy: bounded bucket authorization happens BEFORE total/page and action fetch.
    final class Be014bFakePolicyRepo implements PolicyPageReadRepositoryPort
    {
        public bool $supported = true;
        public ?array $buckets = [
            ['id' => 11, 'organization_id' => 1, 'status' => 'ACTIVE'],
            ['id' => 22, 'organization_id' => 2, 'status' => 'INACTIVE'],
        ];
        public array $allowed = [];
        public array $actionIds = [];
        public array $events = [];

        public function supportsSqlPolicyList(CommonListQuery $query): bool
        {
            $this->events[] = 'supports';
            return $this->supported;
        }
        public function findPolicyVisibilityBuckets(int $maxBuckets = 64): ?array
        {
            $this->events[] = 'buckets';
            return $this->buckets;
        }
        public function findAuthorizedPolicyPage(CommonListQuery $query, array $allowedBuckets): array
        {
            $this->events[] = 'page';
            $this->allowed = $allowedBuckets;
            return ['total' => 2, 'rows' => [['id' => 11], ['id' => 11], ['id' => 33]]];
        }
        public function findAllPolicyActionsForPolicies(array $policyIds): array
        {
            $this->events[] = 'actions';
            $this->actionIds = $policyIds;
            return [['policy_id' => 11, 'code' => 'view']];
        }
    }
    $repo = new Be014bFakePolicyRepo();
    verify014b(is_subclass_of(\App\Repositories\PolicyRepository::class, PolicyPageReadRepositoryPort::class), 'legacy Policy repository implements canonical read port');
    $reader = new AuthorizedPolicyPageReader($repo);
    $listQuery = $queryParser->parse(['sort' => 'id'], new CommonListQueryAllowlist([], [], ['id']));
    $authorizations = [];
    $authorize = static function (array $bucket) use (&$authorizations): void {
        $authorizations[] = $bucket['id'];
        if ($bucket['id'] === 22) {
            throw new DomainException('AUTH_POLICY_DENIED', ['action' => 'view']);
        }
    };
    $visibility = static fn (DomainException $exception): bool => $exception->getErrorCode() === 'AUTH_POLICY_DENIED';
    $result = $reader->tryList($listQuery, $authorize, $visibility);
    verify014b($result !== null, 'SQL Policy result returned');
    same014b([11, 22], $authorizations, 'every visibility bucket evaluated');
    same014b([['id' => 11, 'organization_id' => 1, 'status' => 'ACTIVE']], $repo->allowed, 'only authorized buckets applied to SQL COUNT');
    same014b(['supports', 'buckets', 'page', 'actions'], $repo->events, 'authorization before COUNT/actions');
    same014b([11, 33], $repo->actionIds, 'actions loaded only for unique page policy IDs');
    same014b([['policy_id' => 11, 'code' => 'view']], $result->data[0]['actions'], 'Policy action projection');
    same014b([], $result->data[2]['actions'], 'unrelated Policy has no actions');
    same014b('2', $result->total, 'count is string');
    $repo->supported = false;
    $repo->events = [];
    same014b(null, $reader->tryList($listQuery, $authorize, $visibility), 'unsupported SQL falls back');
    same014b(['supports'], $repo->events, 'unsupported SQL never authorizes/loads rows');
    $repo->supported = true;
    $repo->buckets = null;
    $repo->events = [];
    same014b(null, $reader->tryList($listQuery, $authorize, $visibility), 'unsafe buckets fall back');
    same014b(['supports', 'buckets'], $repo->events, 'unsafe buckets never paginate');
    $repo->buckets = [['id' => 11, 'organization_id' => 1, 'status' => 'ACTIVE']];
    $repo->events = [];
    error014b(fn () => $reader->tryList($listQuery, static fn () => throw new DomainException('UNEXPECTED_AUTH_FAILURE'), $visibility), DomainException::class, 'UNEXPECTED_AUTH_FAILURE');
    same014b(['supports', 'buckets'], $repo->events, 'non-visibility error propagates before SQL');

    foreach ([
        'Modules/Authorization/Administration/Policies/Application/AuthorizedPolicyPageReader.php',
        'Modules/Product/Import/ProductImportReadQueryPolicy.php',
        'Modules/Product/Import/ProductImportResponsePresenter.php',
        'Modules/Settings/Display/Application/DisplaySettingsInputNormalizer.php',
        'Modules/Settings/Display/Application/DisplaySettingsResponsePresenter.php',
    ] as $path) {
        $source = (string) file_get_contents($app . $path);
        verify014b(! str_contains($source, 'use App\\Services\\Api'), 'No reverse dependency to API facade in ' . $path);
        verify014b(! str_contains($source, 'db_connect('), 'No implicit DB in extracted helper ' . $path);
    }
    $source = (string) file_get_contents($app . 'Services/Api030AuthorizationWorkflowService.php');
    verify014b(str_contains($source, 'withReadOnlyBatch($operation') && str_contains($source, '$this->policyPageReader()->tryList('), 'Policy API keeps read snapshot and delegates');

    echo "SERVICES DECOMPOSITION BOUNDARY PASS: Settings validation/projection, Import query/SQL guard/projection, Policy authorized-bucket pagination; no DB.\n";
}
