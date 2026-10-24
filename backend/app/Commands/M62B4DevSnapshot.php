<?php

declare(strict_types=1);

namespace App\Commands;

use App\Modules\Numbering\Configuration\NumberingRules;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\Settings\Config\Services as SettingsServices;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/** SELECT-only snapshot: current default Product series, counter and nonce-owned fixtures. */
final class M62B4DevSnapshot extends BaseCommand
{
    protected $group = 'DinkERP';
    protected $name = 'm62b4:snapshot';
    protected $description = 'Read-only DEV Product AUTO numbering readiness and fixture snapshot.';

    /** @param list<string> $params */
    public function run(array $params): void
    {
        require_once ROOTPATH.'tools/m62b4/M62B4Plan.php';
        if (count($params)!==1) throw new \InvalidArgumentException('Usage: php spark m62b4:snapshot M62B4_XXXXXXXXXXXX');
        $marker=\M62B4Plan::assertMarker((string)$params[0]);
        $result=['ready'=>false, 'reason'=>'UNRESOLVED', 'marker'=>$marker,
            'series_id'=>null,'series_code'=>null,'scope_id'=>null,'period_key'=>null,
            'counter_id'=>null,'last_value'=>null,'start_number'=>null,'increment_by'=>null,
            'products'=>['a'=>null,'b'=>null,'rollback'=>null], 'simple_combo_components'=>0];
        try {
            $db=db_connect();
            $resource=$db->table('resources')->select('id')->where('type','product')->get()->getResultArray();
            if (count($resource)!==1) throw new \RuntimeException('RESOURCE_NOT_UNIQUE');
            $series=$db->table('auto_numbering_series')->where('resource_id',$resource[0]['id'])
                ->where('status','ACTIVE')->where('is_default',1)->get()->getResultArray();
            if (count($series)!==1) throw new \RuntimeException('AUTO_DEFAULT_MISSING_OR_AMBIGUOUS');
            $s=$series[0];
            if (!in_array(strtoupper((string)$s['generation_mode']),['AUTO_REQUIRED','AUTO_IF_EMPTY'],true)) {
                throw new \RuntimeException('DEFAULT_NOT_AUTO');
            }
            if (strtoupper((string)$s['scope_type'])!=='GROUP') {
                throw new \RuntimeException('DEFAULT_NOT_GROUP_SCOPED');
            }
            NumberingRules::validatePattern($s);
            $roots=$db->table('organizations')->where('type','GROUP')->where('parent_id',null)->get()->getResultArray();
            if (count($roots)!==1 || strtoupper((string)$roots[0]['status'])!=='ACTIVE') {
                throw new \RuntimeException('GROUP_ROOT_INVALID');
            }
            $root=$roots[0];
            $settings=SettingsServices::settings();
            try { $zone=$settings->get('App.businessTimezone','group:'.$root['id']); }
            catch (\CodeIgniter\Database\Exceptions\DatabaseException) { $zone=null; }
            if (!is_string($zone)||$zone==='') {
                try { $zone=$settings->get('App.businessTimezone'); }
                catch (\CodeIgniter\Database\Exceptions\DatabaseException) { $zone=null; }
            }
            $when=new DateTimeImmutable('now',new DateTimeZone(is_string($zone)&&$zone!==''?$zone:'Asia/Ho_Chi_Minh'));
            $period=match(strtoupper((string)$s['reset_policy'])) {
                'NEVER'=>'ALL','YEARLY'=>$when->format('Y'),
                'MONTHLY'=>$when->format('Ym'),'DAILY'=>$when->format('Ymd'),
                default=>throw new \RuntimeException('RESET_POLICY_INVALID'),
            };
            $result['series_id']=(string)$s['id'];
            $result['series_code']=(string)$s['series_code'];
            $result['scope_id']=(string)$root['id'];
            $result['period_key']=$period;
            $result['start_number']=(int)$s['start_number'];
            $result['increment_by']=(int)$s['increment_by'];
            if ($result['start_number']<0 || $result['increment_by']<1) {
                throw new \RuntimeException('COUNTER_CONFIGURATION_INVALID');
            }
            $counter=$db->table('auto_numbering_counters')->select('id,last_value')
                ->where('numbering_series_id',$s['id'])->where('scope_organization_id',$root['id'])
                ->where('period_key',$period)->get()->getRowArray();
            if (is_array($counter)) {
                $result['counter_id']=(string)$counter['id'];
                $result['last_value']=(int)$counter['last_value'];
            }
            foreach (['a','b','rollback'] as $kind) {
                $sku=$kind==='rollback'?'DEV_'.$marker.'_ROLLBACK':\M62B4Plan::sku($marker,$kind);
                $matches=$db->table('products')->select('id,code,sku,product_type')->where('sku',$sku)->get()->getResultArray();
                if (count($matches)>1) throw new \RuntimeException('FIXTURE_SKU_AMBIGUOUS');
                if (isset($matches[0])) {
                    $row=$matches[0];
                    if (($kind==='rollback'? 'COMBO':'STANDARD')!==$row['product_type']) {
                        throw new \RuntimeException('FIXTURE_PRODUCT_TYPE_MISMATCH');
                    }
                    $result['products'][$kind]=['id'=>(string)$row['id'],
                        'code'=>(string)$row['code'],'sku'=>(string)$row['sku']];
                    if ($kind!=='rollback') {
                        $n=$db->table('product_combo_items')->where('combo_product_id',$row['id'])->countAllResults();
                        $result['simple_combo_components']+=(int)$n;
                    }
                }
            }
            $result['ready']=true; $result['reason']='PASS';
        } catch (Throwable $e) {
            // Do not print raw SQL/credentials. Stable bounded diagnostic reason only.
            $reason=$e->getMessage();
            $safe=['RESOURCE_NOT_UNIQUE','AUTO_DEFAULT_MISSING_OR_AMBIGUOUS','DEFAULT_NOT_AUTO',
                'DEFAULT_NOT_GROUP_SCOPED','GROUP_ROOT_INVALID','RESET_POLICY_INVALID',
                'COUNTER_CONFIGURATION_INVALID','FIXTURE_SKU_AMBIGUOUS','FIXTURE_PRODUCT_TYPE_MISMATCH'];
            $result['reason']=in_array($reason,$safe,true)?$reason:'NUMBERING_CONFIGURATION_UNSUPPORTED';
        }
        \CodeIgniter\CLI\CLI::write('M6.2B.4 READ-ONLY NUMBERING: '.($result['ready']?'READY':'BLOCKED').'; reason='.$result['reason']);
        \CodeIgniter\CLI\CLI::write('M62B4_SNAPSHOT_JSON:'.json_encode($result,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    }
}
