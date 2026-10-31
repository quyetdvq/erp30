<?php

declare(strict_types=1);

namespace App\Modules\Numbering\Configuration;

use App\Exceptions\DomainException;
use App\Modules\Access\UnifiedAccessService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use RuntimeException;
use Throwable;

/** Configuration API only: no counter allocation or issuance during validation. */
final class NumberingService
{
    private readonly BaseConnection $db;
    private readonly UnifiedAccessService $access;
    private readonly string $actorId;

    public function __construct(User $actor, ?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
        $this->access = new UnifiedAccessService($actor,$this->db);
        $this->actorId = (string) $actor->id;
    }

    /**
     * One read model for the existing Auto Numbering screen. Evaluates the
     * same persisted Resource VIEW + configure_numbering PBAC predicates as
     * the old Resource catalog and its scoped numbering-series endpoints.
     * SQL reads are batched; no per-Resource numbering-series request/query.
     *
     * @return array{resources:list<array<string,mixed>>,series:list<array<string,mixed>>}
     */
    public function overview(): array
    {
        $resourceRows = $this->db->table('resources')
            ->orderBy('type', 'ASC')->orderBy('id', 'ASC')->get();
        if ($resourceRows === false) throw new RuntimeException('Numbering Resource catalog read failed');
        return NumberingOverview::assemble(
            $resourceRows->getResultArray(),
            fn (array $resource): bool =>
                $this->access->canView('resource', $resource) &&
                $this->access->decideMutation('resource', 'configure_numbering', $resource)['allowed'] === true,
            function (array $ids): array {
                $all = [];
                // Avoid oversized IN lists for large catalogs; complexity is
                // O(ceil(N/200)) SQL SELECTs, never O(N) HTTP requests.
                foreach (array_chunk($ids, 200) as $batch) {
                    $result = $this->db->table('auto_numbering_series')
                        ->whereIn('resource_id', $batch)->orderBy('id', 'ASC')->get();
                    if ($result === false) throw new RuntimeException('Numbering overview series read failed');
                    array_push($all, ...$result->getResultArray());
                }
                return $all;
            },
        );
    }

    /** @return list<array<string,mixed>> */
    public function listing(string $resourceId): array
    {
        $resource = $this->resource($resourceId);
        $this->authorize($resource);
        $rows = $this->db->table('auto_numbering_series')->where('resource_id',$resourceId)->orderBy('id','ASC')->get()->getResultArray();
        return array_map([NumberingRules::class,'visible'],$rows);
    }

    /** @return array<string,mixed> */
    public function detail(string $resourceId, string $seriesId): array
    {
        $this->authorize($this->resource($resourceId));
        return NumberingRules::visible($this->series($resourceId,$seriesId));
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function create(string $resourceId, array $payload): array
    {
        $resourceId = NumberingRules::id($resourceId);
        $payload['resource_id'] = $resourceId; // Route always outranks untrusted JSON.
        $data = NumberingRules::normalize($payload, true);
        $id = $this->transaction(function () use ($resourceId, $data): string {
            $resource = $this->resource($resourceId, true);
            $this->authorize($resource);
            $this->validateCandidate($resourceId,$data,null);
            $audit = ['created_by'=>$this->actorId,'created_at'=>gmdate('Y-m-d H:i:s')];
            if (!$this->db->table('auto_numbering_series')->insert([...$data,...$audit])) {
                throw new RuntimeException('Numbering series insert failed');
            }
            return (string) $this->db->insertID();
        });
        return $this->detail($resourceId,$id);
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function update(string $resourceId, string $seriesId, array $payload): array
    {
        $resourceId = NumberingRules::id($resourceId);
        $seriesId = NumberingRules::id($seriesId);
        $patch = NumberingRules::normalize($payload,false);
        $this->transaction(function () use ($resourceId,$seriesId,$patch): void {
            $resource = $this->resource($resourceId,true);
            $this->authorize($resource);
            $current = $this->series($resourceId,$seriesId,true);
            $candidate = array_replace($current,$patch);
            if ((string) ($candidate['resource_id'] ?? '') !== $resourceId) {
                throw new DomainException('NUM_INVALID_CONFIGURATION', ['field'=>'resource_id']);
            }
            $this->assertIssuedFieldsUnchanged($seriesId,$current,$candidate);
            $this->validateCandidate($resourceId,$candidate,$seriesId);
            if (!$this->db->table('auto_numbering_series')->where('id',$seriesId)->where('resource_id',$resourceId)
                    ->update([...$patch, 'updated_by'=>$this->actorId,'updated_at'=>gmdate('Y-m-d H:i:s')])) {
                throw new RuntimeException('Numbering series update failed');
            }
        });
        return $this->detail($resourceId,$seriesId);
    }

    /** @return array<string,mixed> */
    public function setState(string $resourceId, string $seriesId, mixed $status): array
    {
        return $this->update($resourceId,$seriesId,['status'=>$status]);
    }

    /** @param array<string,mixed> $payload @return array{valid:bool,normalized_pattern:string,validation_messages:list<string>} */
    public function validateConfig(string $resourceId, array $payload): array
    {
        $resourceId = NumberingRules::id($resourceId);
        $this->authorize($this->resource($resourceId));
        // Optional EDIT context; CREATE requests omit series_id entirely.
        // Do not trust an arbitrary exclusion: verify the series belongs to the
        // route-scoped Resource, and enforce issuance locks just like PATCH.
        $seriesId = array_key_exists('series_id',$payload) ? NumberingRules::id($payload['series_id']) : null;
        unset($payload['series_id']);
        $payload['resource_id'] = $resourceId;
        $candidate = NumberingRules::normalize($payload, true);
        if ($seriesId !== null) {
            $current = $this->series($resourceId,$seriesId);
            $this->assertIssuedFieldsUnchanged($seriesId,$current,$candidate);
        }
        $this->validateCandidate($resourceId,$candidate,$seriesId);
        return ['valid'=>true,'normalized_pattern'=>(string) $candidate['pattern'],'validation_messages'=>[]];
    }

    /** @param array<string,mixed> $current @param array<string,mixed> $candidate */
    private function assertIssuedFieldsUnchanged(string $seriesId, array $current, array $candidate): void
    {
        $counters = $this->db->table('auto_numbering_counters')->select('id')
            ->where('numbering_series_id',$seriesId)->limit(1)->get()->getRowArray();
        if ($counters === null) return;
        foreach (['resource_id','series_code','scope_type','reset_policy'] as $field) {
            if ((string) $candidate[$field] !== (string) $current[$field]) {
                throw new DomainException('NUM_ISSUED_FIELD_IMMUTABLE', ['field'=>$field]);
            }
        }
    }

    /** @param array<string,mixed> $candidate */
    private function validateCandidate(string $resourceId, array $candidate, ?string $exceptId): void
    {
        // Match the baseline canonical validator: same token/literal structure
        // collides even when the SEQ padding widths differ (e.g. 3 vs 5).
        $signature = NumberingRules::collisionSignature($candidate);
        foreach ($this->db->table('auto_numbering_series')->where('resource_id',$resourceId)->get()->getResultArray() as $old) {
            if ($exceptId !== null && (string) $old['id']===$exceptId) continue;
            if ((string) $old['series_code'] === (string) $candidate['series_code']) {
                throw new DomainException('NUM_SERIES_DUPLICATE', ['field'=>'series_code']);
            }
            if ($candidate['status']==='ACTIVE' && (bool) $candidate['is_default'] &&
                (string) $old['status']==='ACTIVE' && (bool) $old['is_default']) {
                throw new DomainException('NUM_ACTIVE_DEFAULT_CONFLICT', ['field'=>'is_default']);
            }
            if ($signature !== null && (string) $old['status']==='ACTIVE' &&
                NumberingRules::collisionSignature($old)===$signature) {
                throw new DomainException('NUM_PATTERN_COLLISION', ['field'=>'pattern']);
            }
        }
    }

    /** @return array<string,mixed> */
    private function resource(string $id, bool $lock = false): array
    {
        $id = NumberingRules::id($id);
        $query = $this->db->table('resources')->where('id',$id);
        $rows = $lock ? $this->db->query($query->getCompiledSelect().' FOR UPDATE') : $query->get();
        if ($rows === false) throw new RuntimeException('Numbering resource lookup failed');
        $row = $rows->getRowArray();
        if ($row === null) throw new DomainException('NUM_RESOURCE_NOT_FOUND');
        return $row;
    }

    /** @return array<string,mixed> */
    private function series(string $resourceId, string $id, bool $lock = false): array
    {
        $resourceId = NumberingRules::id($resourceId);
        $id = NumberingRules::id($id);
        $query = $this->db->table('auto_numbering_series')->where('id',$id)->where('resource_id',$resourceId);
        $rows = $lock ? $this->db->query($query->getCompiledSelect().' FOR UPDATE') : $query->get();
        if ($rows === false) throw new RuntimeException('Numbering series lookup failed');
        $row = $rows->getRowArray();
        if ($row === null) throw new DomainException('NUM_SERIES_NOT_FOUND');
        return $row;
    }

    /** @param array<string,mixed> $persisted */
    private function authorize(array $persisted): void
    {
        $decision = $this->access->decideMutation('resource','configure_numbering',$persisted);
        if (!$decision['allowed']) throw new DomainException('NUM_AUTHORIZATION_DENIED');
    }

    /** @template T @param callable():T $callback @return T */
    private function transaction(callable $callback): mixed
    {
        if (!$this->db->transBegin()) throw new RuntimeException('Numbering transaction start failed');
        try {
            $result = $callback();
            if (!$this->db->transStatus() || !$this->db->transCommit()) throw new RuntimeException('Numbering transaction commit failed');
            return $result;
        } catch (Throwable $e) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            throw $e;
        }
    }
}
