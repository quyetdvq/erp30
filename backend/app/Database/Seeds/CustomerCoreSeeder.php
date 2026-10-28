<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use RuntimeException;
use Throwable;

/** P2-G1: provision resources, lifecycle and actions; never grant permissions. */
final class CustomerCoreSeeder extends Seeder
{
    public function run(): void
    {
        if (! $this->db->tableExists('customers')) {
            throw new RuntimeException('CUS_MIGRATION_REQUIRED_BEFORE_SEED');
        }
        if (! $this->db->transBegin()) throw new RuntimeException('CUS_SEED_TRANSACTION_FAILED');
        try {
            $lifecycle = $this->ensureLifecycle();
            $contactLifecycle = $this->contactLifecycle($lifecycle);
            foreach ([
                'customer'=>['MASTER',['view','create','edit','setstate','export'],$lifecycle],
                'customer_contact'=>['MASTER',['view','create','edit','setstate'],$contactLifecycle],
                'customer_interaction'=>['ORGANIZATION',['view','create','export'],null],
            ] as $type=>$config) {
                [$domain,$actions,$targetLifecycle] = $config;
                $resource = $this->db->table('resources')->where('type',$type)->get()->getRowArray();
                if ($resource === null) {
                    if (! $this->db->table('resources')->insert([
                        'type'=>$type,'name'=>$type,'domain'=>$domain,'lifecycle_id'=>$targetLifecycle,
                        'status'=>'ACTIVE','created_at'=>gmdate('Y-m-d H:i:s'),
                    ])) throw new RuntimeException('CUS_RESOURCE_SEED_FAILED');
                    $resourceId=(int)$this->db->insertID();
                } else {
                    if ($resource['domain'] !== $domain
                        || (string)($resource['lifecycle_id']??'') !== (string)($targetLifecycle??'')) {
                        throw new RuntimeException('CUS_RESOURCE_CONFLICT: '.$type);
                    }
                    $resourceId=(int)$resource['id'];
                }
                foreach ($actions as $action) {
                    if ($this->db->table('resource_actions')->where('resource_id',$resourceId)
                        ->where('code',$action)->countAllResults()===0) {
                        if (!$this->db->table('resource_actions')->insert([
                            'resource_id'=>$resourceId,'code'=>$action,'created_at'=>gmdate('Y-m-d H:i:s'),
                        ])) throw new RuntimeException('CUS_ACTION_SEED_FAILED');
                    }
                }
            }
            if (!$this->db->transStatus() || !$this->db->transCommit()) throw new RuntimeException('CUS_SEED_FAILED');
        } catch (Throwable $error) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            throw $error;
        }
    }

    /** Preserve P1 Contact lifecycle where it is configured consistently for both existing owner types. */
    private function contactLifecycle(int $fallback): int
    {
        $rows=$this->db->table('resources')->select('type,lifecycle_id')
            ->whereIn('type',['organization_contact','employee_contact'])->get()->getResultArray();
        if (count($rows)!==2) throw new RuntimeException('CUS_P1_CONTACT_RESOURCES_MISSING');
        $ids=[];
        foreach ($rows as $row) $ids[$row['type']]=$row['lifecycle_id'];
        if ((string)($ids['organization_contact']??'')!==(string)($ids['employee_contact']??''))
            throw new RuntimeException('CUS_CONTACT_LIFECYCLE_MISMATCH');
        $chosen=$ids['organization_contact']??null;
        return $chosen===null?$fallback:(int)$chosen;
    }

    private function ensureLifecycle(): int
    {
        $now=gmdate('Y-m-d H:i:s');
        $record=$this->db->table('lifecycles')->where('code','CUS_ACTIVE_INACTIVE')->get()->getRowArray();
        if ($record === null) {
            if (!$this->db->table('lifecycles')->insert([
                'code'=>'CUS_ACTIVE_INACTIVE','name'=>'Customer Active/Inactive',
                'status'=>'ACTIVE','created_at'=>$now,
            ])) throw new RuntimeException('CUS_LIFECYCLE_SEED_FAILED');
            $id=(int)$this->db->insertID();
        } else {
            if ($record['status'] !== 'ACTIVE') throw new RuntimeException('CUS_LIFECYCLE_INACTIVE');
            $id=(int)$record['id'];
        }
        $ids=[];
        foreach (['ACTIVE','INACTIVE'] as $index=>$code) {
            $state=$this->db->table('workflow_states')->where('code',$code)->where('status','ACTIVE')->get()->getRowArray();
            if ($state===null) throw new RuntimeException('CUS_WORKFLOW_STATE_MISSING: '.$code);
            $ids[$code]=(int)$state['id'];
            $member=$this->db->table('lifecycle_states')->where('lifecycle_id',$id)->where('state_id',$ids[$code])->get()->getRowArray();
            if ($member===null) {
                if (!$this->db->table('lifecycle_states')->insert([
                    'lifecycle_id'=>$id,'state_id'=>$ids[$code],'sequence'=>$index+1,
                    'is_initial'=>$code==='ACTIVE'?1:0,'created_at'=>$now,
                ])) throw new RuntimeException('CUS_LIFECYCLE_MEMBER_FAILED');
            } elseif ((int)$member['is_initial']!==($code==='ACTIVE'?1:0)) {
                throw new RuntimeException('CUS_LIFECYCLE_INITIAL_CONFLICT');
            }
        }
        foreach ([['ACTIVE','INACTIVE'],['INACTIVE','ACTIVE']] as [$from,$to]) {
            $match=$this->db->table('lifecycle_transitions')->where('lifecycle_id',$id)
                ->where('from_state_id',$ids[$from])->where('to_state_id',$ids[$to])
                ->where('action_code','setstate')->get()->getRowArray();
            if ($match===null) {
                if (!$this->db->table('lifecycle_transitions')->insert([
                    'lifecycle_id'=>$id,'from_state_id'=>$ids[$from],
                    'to_state_id'=>$ids[$to],'action_code'=>'setstate',
                    'status'=>'ACTIVE','created_at'=>$now,
                ])) throw new RuntimeException('CUS_LIFECYCLE_TRANSITION_FAILED');
            } elseif ($match['status']!=='ACTIVE') throw new RuntimeException('CUS_LIFECYCLE_TRANSITION_INACTIVE');
        }
        return $id;
    }
}
