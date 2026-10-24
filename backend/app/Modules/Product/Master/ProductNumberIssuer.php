<?php

declare(strict_types=1);

namespace App\Modules\Product\Master;

use App\Exceptions\DomainException;
use App\Modules\Numbering\Configuration\NumberingRules;
use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/** Product create numbering participant; parent transaction owns counter and business insert. */
final class ProductNumberIssuer
{
    public function __construct(private readonly BaseConnection $db) {}

    /**
     * @param ?string $requestedSeries
     * @param ?string $manualCode
     * @param callable(string):string $persist Callback inserts the product and returns its ID.
     */
    public function issue(?string $requestedSeries, ?string $manualCode, callable $persist): string
    {
        // The caller already owns a physical transaction and a Product-wide advisory mutex.
        if ($this->db->transDepth < 1) throw new DomainException('NUM_COUNTER_TRANSACTION_FAILED');
        $resource = $this->db->table('resources')->select('id')->where('type','product')->get()->getRowArray();
        if ($resource === null) throw new DomainException('NUM_IDENTIFIER_RESOURCE_NOT_FOUND');
        $series = null;
        if ($requestedSeries !== null) {
            $series = $this->db->table('auto_numbering_series')->where('resource_id',$resource['id'])
                ->where('series_code',$requestedSeries)->get()->getRowArray();
            if ($series === null) throw new DomainException('NUM_IDENTIFIER_SERIES_NOT_FOUND');
            if (strtoupper((string)$series['status']) !== 'ACTIVE') throw new DomainException('NUM_IDENTIFIER_SERIES_INACTIVE');
        } else {
            $active = $this->db->table('auto_numbering_series')->where('resource_id',$resource['id'])
                ->where('status','ACTIVE')->where('is_default',1)->get()->getResultArray();
            if (count($active)>1) throw new DomainException('NUM_IDENTIFIER_DEFAULT_AMBIGUOUS');
            $series = $active[0] ?? null;
        }
        if ($series === null) {
            if ($manualCode === null) throw new DomainException('PRODUCT_REQUIRED_FIELD', ['field'=>'product_code']);
            return $persist($manualCode);
        }
        $mode = strtoupper((string)$series['generation_mode']);
        if ($manualCode !== null) {
            if ($mode !== 'AUTO_IF_EMPTY') throw new DomainException('NUM_IDENTIFIER_MANUAL_NOT_ALLOWED');
            return $persist($manualCode);
        }
        if (! in_array($mode,['AUTO_REQUIRED','AUTO_IF_EMPTY'],true)) {
            throw new DomainException('NUM_COUNTER_CONFIGURATION_INVALID');
        }
        NumberingRules::validatePattern($series);
        // MASTER Product numbering in the accepted v1.2.4 catalog is GROUP-scoped.
        // Fail closed rather than invent a client-derived COMPANY/ORGANIZATION context.
        $scope=strtoupper((string)$series['scope_type']);
        // Product authorization resolves to GROUP. COMPANY cannot be inferred
        // from an untrusted HTTP hint. ORGANIZATION may resolve to the GROUP
        // root only for a timeless pattern without company dependencies.
        if ($scope==='COMPANY') throw new DomainException('NUM_COMPANY_CONTEXT_REQUIRED');
        if (!in_array($scope,['GROUP','ORGANIZATION'],true)) throw new DomainException('NUM_SCOPE_CONTEXT_REQUIRED');
        if ($scope==='ORGANIZATION' && (strtoupper((string)$series['reset_policy'])!=='NEVER'
            || preg_match('/\{(?:YYYY|YY|MM|DD|COMPANY_CODE)\}/',$series['pattern'])===1)) {
            throw new DomainException('NUM_COMPANY_CONTEXT_REQUIRED');
        }
        $roots = $this->db->table('organizations')->where('type','GROUP')->where('parent_id',null)->get()->getResultArray();
        if (count($roots)!==1 || strtoupper((string)($roots[0]['status']??''))!=='ACTIVE') {
            throw new DomainException('NUM_GROUP_ROOT_INVALID');
        }
        $organization = $roots[0];
        $scopeId=(string)$organization['id'];
        $zone=$this->businessTimezone($scopeId);
        $when=new DateTimeImmutable('now', $zone);
        $period=match (strtoupper((string)$series['reset_policy'])) {
            'NEVER'=>'ALL', 'YEARLY'=>$when->format('Y'), 'MONTHLY'=>$when->format('Ym'), 'DAILY'=>$when->format('Ymd'),
            default=>throw new DomainException('NUM_SCOPE_INVALID'),
        };
        $counter=$this->db->table('auto_numbering_counters')
            ->where('numbering_series_id',$series['id'])->where('scope_organization_id',$scopeId)
            ->where('period_key',$period)->get()->getRowArray();
        $candidate=$counter===null ? (int)$series['start_number'] : (int)$counter['last_value']+(int)$series['increment_by'];
        $increment=(int)$series['increment_by'];
        if ($candidate<0 || $increment<1) throw new DomainException('NUM_COUNTER_CONFIGURATION_INVALID');
        $id = null;
        $issued = false;
        for ($i=0; $i<1000; $i++) {
            if ($candidate<0 || $candidate>PHP_INT_MAX-$increment) throw new DomainException('NUM_COUNTER_OVERFLOW');
            $code = self::render((string)$series['pattern'],$candidate,$when,(string)$organization['code']);
            if (strlen($code)>50) throw new DomainException('NUM_IDENTIFIER_TOO_LONG');
            if ($this->db->table('products')->select('id')->where('code',$code)->get()->getRowArray()===null) {
                $id = $persist($code);
                $issued=true;
                break;
            }
            $candidate += $increment;
        }
        if (! $issued || $id === null) throw new DomainException('NUM_COUNTER_CANDIDATE_EXHAUSTED');
        $now=gmdate('Y-m-d H:i:s');
        if ($counter===null) {
            $ok=$this->db->table('auto_numbering_counters')->insert([
                'numbering_series_id'=>$series['id'], 'scope_organization_id'=>$scopeId,
                'period_key'=>$period, 'last_value'=>$candidate,'created_at'=>$now,'updated_at'=>$now,
            ]);
        } else {
            $ok=$this->db->table('auto_numbering_counters')->where('id',$counter['id'])
                ->update(['last_value'=>$candidate,'updated_at'=>$now]);
        }
        if (! $ok) throw new DomainException('NUM_COUNTER_TRANSACTION_FAILED');
        return $id;
    }

    public static function render(string $pattern, int $sequence, DateTimeImmutable $now, string $orgCode): string
    {
        return (string)preg_replace_callback('/\{([^{}]*)\}/', static function(array $m) use ($sequence,$now,$orgCode): string {
            return match (true) {
                preg_match('/\ASEQ:([0-9]+)\z/D',$m[1],$width)===1 => str_pad((string)$sequence,(int)$width[1],'0',STR_PAD_LEFT),
                $m[1]==='YYYY' => $now->format('Y'), $m[1]==='YY' => $now->format('y'),
                $m[1]==='MM' => $now->format('m'), $m[1]==='DD' => $now->format('d'),
                $m[1]==='ORG_CODE' => $orgCode, $m[1]==='RESOURCE' => 'product',
                default => throw new DomainException('NUM_COUNTER_RUNTIME_CONTEXT_REQUIRED', ['field'=>'pattern']),
            };
        },$pattern);
    }

    private function businessTimezone(string $groupId): DateTimeZone
    {
        try {
            $settings=\CodeIgniter\Settings\Config\Services::settings();
            try { $zone=$settings->get('App.businessTimezone','group:'.$groupId); }
            catch (\CodeIgniter\Database\Exceptions\DatabaseException) { $zone=null; }
            if (!is_string($zone) || $zone==='') {
                try { $zone=$settings->get('App.businessTimezone'); }
                catch (\CodeIgniter\Database\Exceptions\DatabaseException) { $zone=null; }
            }
            return new DateTimeZone(is_string($zone) && $zone!=='' ? $zone : 'Asia/Ho_Chi_Minh');
        } catch (Throwable $cause) {
            throw new DomainException('NUM_BUSINESS_TIMEZONE_RESOLUTION_FAILED', [], $cause);
        }
    }
}
