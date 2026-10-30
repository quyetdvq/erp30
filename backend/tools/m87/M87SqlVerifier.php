<?php

declare(strict_types=1);

use App\Exceptions\DomainException as ErpDomainException;
use App\Modules\Access\Query\AuthorizationSqlScopeCompiler;
use App\Modules\Access\Query\AuthorizedSqlScope;
use App\Modules\Access\UnifiedAccessService;
use App\Modules\Contact\ContactScope;
use App\Modules\Contact\ContactScopeProvider;
use App\Modules\Contact\ContactSql;
use App\Modules\Organization\Read\OrganizationPeopleScopeProvider;
use App\Modules\Organization\Read\OrganizationPeopleSql;
use App\Modules\People\Read\PeopleMasterScopeProvider;
use App\Modules\People\Read\PeopleMasterSql;
use App\Modules\Product\Catalog\ProductCatalogScopeProvider;
use App\Modules\Product\Catalog\ProductCatalogSql;
use App\Modules\Product\Master\ProductReadSql;
use App\Modules\Product\Master\ProductScopeProvider;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use App\Shared\Http\AuthorizedListPage;

require_once __DIR__ . '/M87Plan.php';

/**
 * Bounded DEV-only independent canonical PBAC vs production SQL-plan verifier.
 * All statements are SELECT/EXPLAIN, inside a transaction marked READ ONLY.
 * It does not emulate HTTP authentication or approve production deployment.
 */
final class M87SqlVerifier
{
    private readonly DateTimeImmutable $instant;
    private readonly string $prefix;

    public function __construct(private readonly BaseConnection $db)
    {
        $this->instant = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->prefix = (string)$db->DBPrefix;
        if ($this->prefix !== '' && preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D', $this->prefix) !== 1) {
            throw new RuntimeException('Unsafe database table prefix.');
        }
    }

    /**
     * @param array{actors:list<string>,database:string,max_rows:int,case_file:?string} $options
     * @return array<string,mixed>
     */
    public function verify(array $options): array
    {
        $report = [
            'milestone'=>'M8.7', 'mode'=>'DEV_DB_READ_ONLY', 'timestamp_utc'=>gmdate('c'),
            'status'=>'BLOCKED_NOT_EXECUTED', 'production_acceptance'=>'BLOCKED',
            'http_auth'=>'NOT_TESTED', 'baseline_parity'=>'NOT_TESTED',
            'concurrency'=>'NOT_TESTED', 'actor_ids'=>$options['actors'],
            'max_rows_per_resource'=>$options['max_rows'], 'data'=>[],
        ];
        $realDb = $this->db->query('SELECT DATABASE() AS db_name, VERSION() AS db_version');
        if ($realDb === false) throw new RuntimeException('Cannot attest connected MariaDB database.');
        $info = $realDb->getRowArray();
        $name = (string)($info['db_name'] ?? '');
        M87Plan::databaseMatches($name, $options['database']);
        $report['database'] = $name;
        $report['server_version'] = (string)($info['db_version'] ?? 'unavailable');
        $cases = null;
        if ($options['case_file'] !== null) {
            $decoded = json_decode((string)file_get_contents($options['case_file']), true, 64, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) throw new InvalidArgumentException('Invalid case manifest JSON object.');
            $cases = M87Plan::validateCases($decoded, $options['actors']);
        }

        if ($this->db->transDepth !== 0) throw new RuntimeException('M8.7 must own a fresh read-only transaction.');
        // Both actor snapshots and every compared statement observe the same
        // REPEATABLE READ database snapshot. The DB itself rejects DML here.
        if ($this->db->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ') === false
            || $this->db->query('SET TRANSACTION READ ONLY') === false
            || !$this->db->transBegin()) {
            throw new RuntimeException('Unable to open MariaDB READ ONLY transaction.');
        }
        try {
            $actorModel = model(\App\Modules\Identity\UserModel::class);
            $users = [];
            foreach ($options['actors'] as $actorId) {
                $user = $actorModel->find($actorId);
                if (!$user instanceof User || (int)($user->active ?? 0) !== 1) {
                    throw new RuntimeException('Invalid or inactive DEV actor ' . $actorId . '.');
                }
                $users[$actorId] = $user;
            }

            $issues = [];
            $coverage = [];
            foreach ($users as $actorId => $user) {
                $access = new UnifiedAccessService($user, $this->db, $this->instant);
                $report['data'][(string)$actorId] = [];
                foreach (M87Plan::resources() as $resource => $table) {
                    try {
                        $entry = $this->resource($access, $resource, $table, $options['max_rows']);
                        $assertions = $cases['actors'][(string)$actorId][$resource] ?? [];
                        if ($cases !== null) {
                            $entry['cases'] = $this->assertCases($assertions, $entry);
                            if ($entry['cases']['failures'] > 0) $entry['status'] = 'FAIL_FIXTURE';
                        }
                    } catch (Throwable $error) {
                        // Reviewed scope-cap refusal is intentional fail-closed,
                        // not a PBAC parity mismatch or license to bypass scope.
                        $refused = str_contains($error->getMessage(),'AUTH_SCOPE_COMPILATION_UNSUPPORTED');
                        $entry = ['status'=>$refused?'INCONCLUSIVE_SCOPE_CAP':'FAIL_RUNTIME',
                            'exception'=>get_class($error),
                            'error_code'=>self::sanitizedError($error)];
                    }
                    $report['data'][(string)$actorId][$resource] = self::redact($entry);
                    if (str_starts_with($entry['status'], 'FAIL')) {
                        $issues[] = $actorId . ':' . $resource . ':' . $entry['status'];
                    } elseif ($entry['status'] !== 'PASS_PARITY'
                        || ($entry['oracle_visible'] ?? 0) === 0
                        || ($entry['oracle_hidden'] ?? 0) === 0
                        || ($entry['unicode_case_tested'] ?? false) !== true) {
                        $coverage[] = $actorId . ':' . $resource . ':' . $entry['status'];
                    }
                }
            }
            if (!$this->db->transStatus()) throw new RuntimeException('Read-only audit transaction status failed.');
            // Roll back even on success: no possibility of committing incidental
            // side effects added by a future maintenance change.
            $this->db->transRollback();
            $this->db->resetTransStatus();
            $report['issues'] = $issues;
            $report['coverage_gaps'] = $coverage;
            $report['fixture_manifest_supplied'] = $cases !== null;
            $complete = $issues === [] && $coverage === []
                && M87Plan::hasMinimumCases($cases,$options['actors']);
            $report['status'] = $issues !== [] ? 'FAIL_DEV_SQL_PARITY'
                : ($complete ? 'PASS_DEV_SQL_PARITY' : 'INCONCLUSIVE_DB_FIXTURE_COVERAGE');
            return $report;
        } catch (Throwable $error) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            throw $error;
        }
    }

    /** @return array<string,mixed> */
    private function resource(UnifiedAccessService $access, string $resource, string $table, int $maxRows): array
    {
        $tableSql = '`' . $this->prefix . $table . '`'; // table comes ONLY from fixed M87Plan map
        $totalQuery = $this->db->query('SELECT COUNT(*) AS n FROM ' . $tableSql);
        if ($totalQuery === false) throw new RuntimeException('Source table COUNT failed.');
        $rawCount = self::count($totalQuery->getRowArray()['n'] ?? null);
        if ($rawCount > $maxRows) {
            return ['status'=>'INCONCLUSIVE_OVERSIZE', 'source_rows'=>$rawCount,
                'max_rows'=>$maxRows, 'reason'=>'Audit bound exceeded; do not sample and claim full parity.'];
        }
        $source = $this->db->query('SELECT * FROM ' . $tableSql . ' ORDER BY id ASC LIMIT ?', [$maxRows+1]);
        if ($source === false) throw new RuntimeException('Source rows cannot be selected.');
        $raw = $source->getResultArray();
        if (count($raw) !== $rawCount) throw new RuntimeException('Inconsistent source COUNT within snapshot.');
        if ($raw === []) return ['status'=>'INCONCLUSIVE_EMPTY', 'source_rows'=>0];

        $expected = [];
        $presence = [];
        $visibleRows = [];
        $scope = $resource === 'contact' ? new ContactScope($this->db, $this->instant->format('Y-m-d')) : null;
        foreach ($raw as $row) {
            $id = (string)($row['id'] ?? '');
            if (preg_match('/\A[1-9][0-9]*\z/D', $id) !== 1 || isset($presence[$id])) {
                throw new UnexpectedValueException('Invalid or duplicate source row ID.');
            }
            $canView = $resource === 'contact'
                ? $this->contactOracle($access, $scope, $row)
                : $access->canView($resource, $row);
            $presence[$id] = (bool)$canView;
            if ($canView) { $expected[] = $id; $visibleRows[] = $row; }
        }

        // A list implementation may issue 403 when base VIEW is absent.
        // Do not label it a SQL-parity success without compiling a real scope.
        if ($resource !== 'contact' && !$access->hasBaseViewPermission($resource)) {
            return ['status'=>'INCONCLUSIVE_NO_BASE_PERMISSION','source_rows'=>$rawCount,
                'oracle_visible'=>count($expected), 'oracle_hidden'=>$rawCount-count($expected)];
        }
        $plans = $this->plans($access, $resource);
        $normalized = $plans['normalize'];
        $collection = $plans['collection'];
        $detail = $plans['detail'];
        $countTimes=[];
        for ($i=0;$i<3;$i++) {
            $start=hrtime(true);
            $result=$this->db->query($collection($normalized(1, 100))['count_sql'],
                $collection($normalized(1, 100))['count_binds']);
            $countTimes[]=(hrtime(true)-$start)/1000000;
            if ($result === false) throw new RuntimeException('Scoped COUNT failed.');
            $scopedCount=self::count($result->getRowArray()['total'] ?? null);
            if ($scopedCount !== count($expected)) break;
        }
        $actual = [];
        $firstPlan = $collection($normalized(1,100));
        $pageTimes = [];
        for ($i=0;$i<3;$i++) {
            $start=hrtime(true);
            $result=$this->db->query($firstPlan['rows_sql'], $firstPlan['rows_binds']);
            $pageTimes[]=(hrtime(true)-$start)/1000000;
            if ($result === false) throw new RuntimeException('Scoped first page failed.');
            $pageRows=$result->getResultArray();
            if ($i===0) {
                foreach ($pageRows as $row) $actual[]=(string)($row['id'] ?? '');
            }
        }
        for ($page=2, $pages=(int)ceil($scopedCount/100); $page<=$pages; $page++) {
            $plan=$collection($normalized($page,100));
            $result=$this->db->query($plan['rows_sql'],$plan['rows_binds']);
            if ($result === false) throw new RuntimeException('Scoped subsequent page failed.');
            foreach ($result->getResultArray() as $row) $actual[]=(string)($row['id'] ?? '');
        }
        $comparison=M87Plan::compareIds($expected,$actual);
        $detailPass=true;
        $detailChecks=0;
        foreach ([self::firstId($presence,true),self::firstId($presence,false)] as $id) {
            if ($id === null) continue;
            $query = $detail($id);
            $result=$this->db->query($query['sql'],$query['binds']);
            if ($result === false) throw new RuntimeException('Scoped detail read failed.');
            $found=$result->getRowArray();
            if (($found!==null)!==$presence[$id] || ($found!==null && (string)$found['id']!==$id)) $detailPass=false;
            $detailChecks++;
        }
        $explainCount=$this->explain($firstPlan['count_sql'],$firstPlan['count_binds']);
        $explainPage=$this->explain($firstPlan['rows_sql'],$firstPlan['rows_binds']);
        $scenarios = $this->queryScenarios($resource, $visibleRows, $collection, $plans['query']);
        $complete = $comparison['match'] && $scopedCount===count($expected) && $detailPass
            && $scenarios['failures'] === 0;
        return [
            'status'=>$complete?'PASS_PARITY':'FAIL_PARITY',
            'source_rows'=>$rawCount, 'oracle_visible'=>count($expected),
            'oracle_hidden'=>$rawCount-count($expected),
            'scoped_total'=>$scopedCount, 'comparison'=>$comparison,
            'detail_checks'=>$detailChecks, 'detail_match'=>$detailPass,
            'query_scenarios'=>$scenarios,
            'unicode_case_tested'=>$scenarios['unicode_case_tested'],
            'count_latency'=>M87Plan::timings($countTimes),
            'page_latency'=>M87Plan::timings($pageTimes),
            'explain'=>['count'=>$explainCount,'page'=>$explainPage],
            // For fixture evaluation inside this read-only process only. Scrub before writing.
            '_oracle_presence'=>$presence,
            '_sql_ids'=>$actual,
        ];
    }

    private static function firstId(array $presence, bool $allowed): ?string
    {
        foreach ($presence as $id => $value) if ($value === $allowed) return (string)$id;
        return null;
    }

    private function contactOracle(UnifiedAccessService $access, ?ContactScope $scope, array $row): bool
    {
        if ($scope === null) throw new LogicException('Missing Contact resolver.');
        try { $target=$scope->resolve($row); }
        catch (ErpDomainException $error) {
            if (in_array($error->getErrorCode(), ['CONTACT_OWNER_NOT_FOUND','CONTACT_PRIMARY_SCOPE_UNAVAILABLE',
                'CONTACT_TYPE_UNSUPPORTED','CONTACT_TYPE_REQUIRED','CONTACT_OWNER_REFERENCE_REQUIRED'],true)) {
                return false;
            }
            throw $error;
        }
        if (!$access->canView($target['resource'],$target['target'])) return false;
        if ($target['resource'] !== 'customer_contact') return true;
        // The canonical Contact API also checks VIEW of the owning Customer.
        // ContactScope::resolve() validates parent existence, not parent PBAC.
        $parent = $this->db->table('customers')->where('id', $row['contact_ref_id'])->get()->getRowArray();
        return $parent !== null && $access->canView('customer', $parent);
    }

    /** @return array{normalize:callable,collection:callable,detail:callable} */
    private function plans(UnifiedAccessService $access, string $resource): array
    {
        $compiler=new AuthorizationSqlScopeCompiler($access);
        $prefix=$this->prefix;
        if ($resource==='product') {
            $scope=$compiler->compile(new ProductScopeProvider());
            return [
                'normalize'=>static fn(int $p,int $s):array=>ProductReadSql::normalize(['page'=>$p,'page_size'=>$s,'sort'=>'id']),
                'query'=>static fn(array $q):array=>ProductReadSql::normalize($q),
                'collection'=>static fn(array $n):array=>ProductReadSql::collection($n,$scope,$prefix),
                'detail'=>static fn(string $id):array=>ProductReadSql::detail($id,$scope,$prefix),
            ];
        }
        if (in_array($resource,['product_group','product_uom'],true)) {
            $scope=$compiler->compile(new ProductCatalogScopeProvider($resource));
            return [
                'normalize'=>static fn(int $p,int $s):array=>ProductCatalogSql::normalize($resource,['page'=>$p,'page_size'=>$s,'sort'=>'id']),
                'query'=>static fn(array $q):array=>ProductCatalogSql::normalize($resource,$q),
                'collection'=>static fn(array $n):array=>ProductCatalogSql::collection($resource,$n,$scope,$prefix),
                'detail'=>static fn(string $id):array=>ProductCatalogSql::detail($resource,$id,$scope,$prefix),
            ];
        }
        if (in_array($resource,['organization','employee_assignment'],true)) {
            $scope=$compiler->compile(new OrganizationPeopleScopeProvider($resource,$this->db));
            return [
                'normalize'=>static fn(int $p,int $s):array=>OrganizationPeopleSql::normalize($resource,['page'=>$p,'page_size'=>$s,'sort'=>'id']),
                'query'=>static fn(array $q):array=>OrganizationPeopleSql::normalize($resource,$q),
                'collection'=>static fn(array $n):array=>OrganizationPeopleSql::collection($resource,$n,$scope,$prefix),
                'detail'=>static fn(string $id):array=>OrganizationPeopleSql::detail($resource,$id,$scope,$prefix),
            ];
        }
        if (in_array($resource,['employee','department','position'],true)) {
            $scope=$compiler->compile(new PeopleMasterScopeProvider($resource));
            $instant=$this->instant;
            return [
                'normalize'=>static fn(int $p,int $s):array=>PeopleMasterSql::normalize($resource,['page'=>$p,'page_size'=>$s,'sort'=>'id']),
                'query'=>static fn(array $q):array=>PeopleMasterSql::normalize($resource,$q),
                'collection'=>static fn(array $n):array=>PeopleMasterSql::collection($resource,$n,$scope,$instant,$prefix),
                'detail'=>static fn(string $id):array=>PeopleMasterSql::detail($resource,$id,$scope,$prefix),
            ];
        }
        if ($resource==='contact') {
            $org=$compiler->compile(new ContactScopeProvider('organization_contact',$this->db));
            $emp=$compiler->compile(new ContactScopeProvider('employee_contact',$this->db));
            // M19.25A: mirror all four scopes used by the production ContactService.
            // Omitting these two MASTER scopes silently excludes CUSTOMER contacts
            // from the audit SQL while the PBAC oracle can still see them.
            $customerContact=AuthorizedSqlScope::master(
                'customer_contact',$access->viewableMasterLifecycleStatuses('customer_contact'));
            $customerMaster=AuthorizedSqlScope::master(
                'customer',$access->viewableMasterLifecycleStatuses('customer'));
            $date=$this->instant->format('Y-m-d');
            return [
                'normalize'=>static fn(int $p,int $s):array=>ContactSql::normalize(['page'=>$p,'page_size'=>$s,'sort'=>'id']),
                'query'=>static fn(array $q):array=>ContactSql::normalize($q),
                'collection'=>static fn(array $n):array=>ContactSql::collection(
                    $n,$org,$emp,$date,$prefix,$customerContact,$customerMaster),
                'detail'=>static fn(string $id):array=>ContactSql::detail(
                    $id,$org,$emp,$date,$prefix,$customerContact,$customerMaster),
            ];
        }
        throw new InvalidArgumentException('Unreviewed source in M8.7.');
    }

    /**
     * Compare the legacy in-memory query contract against SQL on the SAME
     * canonical-authorized corpus. This is an audit-only independent oracle,
     * never used by production request handlers.
     * @param list<array<string,mixed>> $visible
     * @return array{checked:int,failures:int,unicode_case_tested:bool}
     */
    private function queryScenarios(string $resource, array $visible, callable $collection, callable $normalize): array
    {
        $queries = [
            ['sort'=>'-id','page'=>1,'page_size'=>7],
            ['sort'=>'-id','page'=>2,'page_size'=>7],
        ];
        if ($visible !== [] && isset($visible[0]['status'])) {
            $queries[] = ['filter'=>['status'=>[(string)$visible[0]['status']]],
                'sort'=>'id','page'=>1,'page_size'=>11];
        }
        $fields = [
            // M8.7A: Product must exercise Unicode q parity; otherwise this
            // resource can never satisfy the audit's mandatory coverage gate.
            'product'=>['product_name','product_child_name','sku','barcode'],
            'product_group'=>['name','code'], 'product_uom'=>['name','code'],
            'organization'=>['name','legal_name','code'],
            'employee_assignment'=>['description'],
            'employee'=>['employee_name','employee_code'],
            'department'=>['name','code'],'position'=>['name','code'],
            'contact'=>['name','email','phone','mobile'],
        ];
        $unicode=false;
        $q=null;
        if (isset($fields[$resource])) {
            foreach ($visible as $row) {
                foreach ($fields[$resource] as $field) {
                    $value=$row[$field]??null;
                    if (!is_string($value) || trim($value)==='') continue;
                    // Prefer actual Unicode data to catch MariaDB-vs-PHP differences.
                    if (preg_match('/[^\x00-\x7F]/',$value)===1) {
                        $q=mb_substr(trim($value),0,4,'UTF-8');
                        $unicode=true;
                        break 2;
                    }
                    if ($q===null) $q=mb_substr(trim($value),0,4,'UTF-8');
                }
            }
        }
        if ($q!==null) $queries[]=['q'=>$q,'page'=>1,'page_size'=>11,'sort'=>'id'];
        $failures=0; $checked=0;
        foreach ($queries as $query) {
            $expected=AuthorizedListPage::paginate($resource,$visible,$query);
            $plan=$collection($normalize($query));
            $sqlTotal=$this->db->query($plan['count_sql'],$plan['count_binds']);
            $sqlRows=$this->db->query($plan['rows_sql'],$plan['rows_binds']);
            if ($sqlTotal===false || $sqlRows===false) throw new RuntimeException('Scenario SQL failed.');
            $total=self::count($sqlTotal->getRowArray()['total']??null);
            $actualIds=array_map(static fn(array $r):string=>(string)$r['id'],$sqlRows->getResultArray());
            $expectedIds=array_map(static fn(array $r):string=>(string)$r['id'],$expected['data']);
            if ($total!==$expected['total'] || $actualIds!==$expectedIds) $failures++;
            $checked++;
        }
        return ['checked'=>$checked,'failures'=>$failures,'unicode_case_tested'=>$unicode];
    }

    /** @param list<mixed> $binds @return array<string,mixed> */
    private function explain(string $sql, array $binds): array
    {
        if (!preg_match('/\ASELECT\s/i', $sql) || str_contains($sql,';')) {
            throw new RuntimeException('M8.7 EXPLAIN accepts SELECT plans only.');
        }
        $query=$this->db->query('EXPLAIN FORMAT=JSON ' . $sql,$binds);
        if ($query===false) throw new RuntimeException('MariaDB EXPLAIN FORMAT=JSON failed.');
        $row=$query->getRowArray();
        if (!is_array($row) || !is_string(reset($row))) throw new RuntimeException('No EXPLAIN JSON returned.');
        return M87Plan::explainSummary((string)reset($row));
    }

    /** @return array{checked:int,failures:int} */
    private function assertCases(mixed $cases, array $entry): array
    {
        if (!is_array($cases) || !isset($entry['_oracle_presence'],$entry['_sql_ids'])) {
            return ['checked'=>0,'failures'=>0];
        }
        $checked=0; $failures=0;
        $sql=array_fill_keys($entry['_sql_ids'],true);
        foreach (['allow'=>true,'deny'=>false] as $lane=>$expected) {
            foreach (($cases[$lane] ?? []) as $id) {
                $id=(string)$id;
                $checked++;
                if (!array_key_exists($id,$entry['_oracle_presence'])
                    || $entry['_oracle_presence'][$id] !== $expected
                    || isset($sql[$id]) !== $expected) $failures++;
            }
        }
        return ['checked'=>$checked,'failures'=>$failures];
    }

    private static function redact(array $entry): array
    {
        unset($entry['_oracle_presence'],$entry['_sql_ids']);
        return $entry;
    }

    private static function sanitizedError(Throwable $error): string
    {
        $message=$error->getMessage();
        if (str_contains($message,'AUTH_SCOPE_COMPILATION_UNSUPPORTED')) return 'AUTH_SCOPE_COMPILATION_UNSUPPORTED';
        if (str_contains($message,'AUTH_SCOPE_PBAC_MISMATCH')) return 'AUTH_SCOPE_PBAC_MISMATCH';
        return 'REVIEW_LOCAL_ERROR_LOG';
    }

    private static function count(mixed $value): int
    {
        $text=is_string($value)||is_int($value)?(string)$value:'';
        if (preg_match('/\A[0-9]+\z/D',$text)!==1 || strlen($text)>strlen((string)PHP_INT_MAX)
            || (strlen($text)===strlen((string)PHP_INT_MAX) && strcmp($text,(string)PHP_INT_MAX)>0)) {
            throw new RuntimeException('Out-of-bounds audit COUNT result.');
        }
        return (int)$text;
    }
}
