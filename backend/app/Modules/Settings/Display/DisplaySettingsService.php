<?php

declare(strict_types=1);

namespace App\Modules\Settings\Display;

use App\Exceptions\DomainException;
use App\Modules\Access\UnifiedAccessService;
use App\Shared\Http\RequestMetadata;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use RuntimeException;
use Throwable;

/** Scoped persisted settings, deterministic inheritance and atomic audit. */
final class DisplaySettingsService
{
    private readonly BaseConnection $db;
    private readonly UnifiedAccessService $access;
    private readonly string $actorId;

    public function __construct(User $actor, ?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
        $this->access = new UnifiedAccessService($actor, $this->db);
        $this->actorId = (string) $actor->id;
    }

    /** @param array<string,mixed> $query @return array<string,mixed> */
    public function runtime(array $query): array
    {
        if (isset($query['organization_id'])) {
            $ctx = DisplaySettingRules::context(['context_type'=>'ORGANIZATION','organization_id'=>$query['organization_id']], false);
            $this->assertOrganizationVisible((string) $ctx['organization_id']);
        } else {
            $ctx = DisplaySettingRules::context(['context_type'=>'SYSTEM'], false);
            $this->root();
        }
        $all = [];
        foreach (DisplaySettingRules::definitions() as $key => $definition) {
            $all[$key] = $this->resolve($key, $ctx);
        }
        return DisplaySettingRules::runtime($all);
    }

    /** @return list<array<string,mixed>> */
    public function definitions(): array
    {
        $this->permit(['type'=>'SYSTEM','organization_id'=>null,'context'=>'system'], false);
        $rows = [];
        foreach (DisplaySettingRules::definitions() as $key => $def) {
            $row = ['key'=>$key,'category'=>DisplaySettingRules::category($key),
                'data_type'=>$def['type'],'default'=>$def['default'],'user_overridable'=>true];
            if ($def['validation'] === 'integer') $row['min'] = 0;
            $rows[] = $row;
        }
        return $rows;
    }

    /** @param array<string,mixed> $query @return list<array<string,mixed>> */
    public function values(array $query): array
    {
        $ctx = DisplaySettingRules::context($query, false);
        $this->permit($ctx, false);
        return $this->rows($ctx, array_keys(DisplaySettingRules::definitions()));
    }

    /** @param array<string,mixed> $payload @return list<array<string,mixed>> */
    public function update(array $payload): array
    {
        $ctx = DisplaySettingRules::context(is_array($payload['context'] ?? null) ? $payload['context'] : [], true);
        $changes = DisplaySettingRules::changes($payload['changes'] ?? null);
        return $this->mutate($ctx, $changes, false);
    }

    /** @param array<string,mixed> $payload @return list<array<string,mixed>> */
    public function reset(array $payload): array
    {
        $ctx = DisplaySettingRules::context(is_array($payload['context'] ?? null) ? $payload['context'] : [], true);
        $keys = DisplaySettingRules::keys($payload['keys'] ?? null);
        return $this->mutate($ctx, array_fill_keys($keys, null), true);
    }

    /** @param array{type:string,organization_id:?string,context:string} $ctx @param array<string,mixed> $changes
     *  @return list<array<string,mixed>> */
    private function mutate(array $ctx, array $changes, bool $reset): array
    {
        $this->permit($ctx, true);
        $this->transaction(function () use ($ctx, $changes, $reset): void {
            // One stable organization/GROUP row serializes concurrent settings writers.
            $orgId = $ctx['organization_id'] ?? $this->root();
            $lock = $this->db->table('organizations')->select('id')->where('id', $orgId)->getCompiledSelect();
            if ($this->db->query($lock.' FOR UPDATE')->getRowArray() === null) {
                throw new DomainException('DISPLAY_SETTING_ORGANIZATION_NOT_FOUND');
            }
            foreach ($changes as $key => $value) {
                $prior = $this->override($key, $ctx['context']);
                if ($reset ? !$prior['exists'] : ($prior['exists'] && $prior['value'] === $value)) continue;
                $parts = $this->coordinates($key, $ctx['context']);
                if ($prior['exists']) {
                    if ($reset) {
                        $ok = $this->db->table('settings')->where($parts)->delete();
                    } else {
                        $ok = $this->db->table('settings')->where($parts)->update([
                            'value'=>$this->encode($value),'type'=>$this->valueType($value),'updated_at'=>gmdate('Y-m-d H:i:s'),
                        ]);
                    }
                } elseif (!$reset) {
                    $ok = $this->db->table('settings')->insert($parts + [
                        'value'=>$this->encode($value),'type'=>$this->valueType($value),
                        'created_at'=>gmdate('Y-m-d H:i:s'),'updated_at'=>gmdate('Y-m-d H:i:s'),
                    ]);
                } else { continue; }
                if (!$ok) throw new RuntimeException('Display Settings write failed');
                $event = [
                    'configuration_type'=>'DISPLAY_SETTING','context_type'=>$ctx['type'],
                    'organization_id'=>$ctx['organization_id'],'setting_key'=>$key,
                    'operation'=>$reset ? 'RESET' : 'SET',
                    'old_value_json'=>$prior['exists'] && $prior['value'] !== null
                        ? json_encode($prior['value'], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE) : null,
                    'new_value_json'=>!$reset && $value !== null
                        ? json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE) : null,
                    'actor_user_id'=>$this->actorId,'request_id'=>RequestMetadata::id(),
                    'created_at'=>gmdate('Y-m-d H:i:s'),
                ];
                if (!$this->db->table('configuration_audit_events')->insert($event)) {
                    throw new RuntimeException('Display Settings audit transaction failed');
                }
            }
        });
        return $this->rows($ctx, array_keys($changes));
    }

    /** @param array{type:string,organization_id:?string,context:string} $ctx */
    private function permit(array $ctx, bool $write): void
    {
        $organization = $ctx['organization_id'] ?? $this->root();
        if ($ctx['organization_id'] !== null && $this->organization($organization) === null) {
            throw new DomainException('DISPLAY_SETTING_ORGANIZATION_NOT_FOUND');
        }
        $row = ['organization_id'=>$organization];
        $decision = $write
            ? $this->access->decideMutation('display_setting', 'edit', $row)
            : $this->access->decidePersisted('display_setting','view',$row);
        if (!$decision['allowed']) throw new DomainException('DISPLAY_SETTING_AUTHORIZATION_DENIED');
    }

    private function assertOrganizationVisible(string $id): void
    {
        $row = $this->organization($id);
        if ($row === null) throw new DomainException('DISPLAY_SETTING_ORGANIZATION_NOT_FOUND');
        if (!$this->access->canView('organization', $row)) {
            throw new DomainException('DISPLAY_SETTING_ORGANIZATION_NOT_FOUND');
        }
    }

    /** @return array<string,mixed>|null */
    private function organization(string $id): ?array
    {
        $result = $this->db->table('organizations')->where('id', $id)->get();
        return $result === false ? throw new RuntimeException('Organization query failed') : $result->getRowArray();
    }

    private function root(): string
    {
        $rows = $this->db->table('organizations')->select('id')->where('type','GROUP')->where('parent_id', null)->limit(2)->get()->getResultArray();
        if (count($rows) !== 1) throw new DomainException('DISPLAY_SETTING_ORGANIZATION_NOT_FOUND');
        return (string) $rows[0]['id'];
    }

    /** @param array{type:string,organization_id:?string,context:string} $ctx @return list<string> */
    private function contexts(array $ctx): array
    {
        $contexts = [];
        $id = $ctx['organization_id'];
        $visited = [];
        for ($depth=0; $id !== null && $depth < 100; $depth++) {
            if (isset($visited[$id])) throw new RuntimeException('Cyclic organization ancestry');
            $visited[$id] = true;
            $row = $this->organization($id);
            if ($row === null) throw new DomainException('DISPLAY_SETTING_ORGANIZATION_NOT_FOUND');
            $contexts[] = 'organization:'.$id;
            $id = $row['parent_id'] === null ? null : (string) $row['parent_id'];
        }
        if ($id !== null) throw new RuntimeException('Organization ancestry too deep');
        $root = $this->root();
        if ($ctx['organization_id'] !== null && !isset($visited[$root])) {
            throw new DomainException('DISPLAY_SETTING_ORGANIZATION_NOT_FOUND');
        }
        $contexts[] = 'system';
        return $contexts;
    }

    /** @param array{type:string,organization_id:?string,context:string} $ctx */
    private function resolve(string $key, array $ctx): mixed
    {
        foreach ($this->contexts($ctx) as $context) {
            $override = $this->override($key, $context);
            if (!$override['exists']) continue;
            try { DisplaySettingRules::validate($key,$override['value']); }
            catch (DomainException) { continue; }
            return $override['value'];
        }
        return DisplaySettingRules::definitions()[$key]['default'];
    }

    /** @param array{type:string,organization_id:?string,context:string} $ctx @param list<string> $keys
     *  @return list<array<string,mixed>> */
    private function rows(array $ctx, array $keys): array
    {
        $contexts = $this->contexts($ctx);
        $out = [];
        foreach ($keys as $key) {
            $direct = $this->override($key, $ctx['context']);
            $directValid = $direct['exists'];
            if ($directValid) {
                try { DisplaySettingRules::validate($key, $direct['value']); }
                catch (DomainException) { $directValid = false; }
            }
            $source = 'system';
            foreach ($contexts as $candidate) {
                $item = $this->override($key, $candidate);
                if (!$item['exists']) continue;
                try { DisplaySettingRules::validate($key, $item['value']); }
                catch (DomainException) { continue; }
                $source = $candidate;
                break;
            }
            $sourceId = str_starts_with($source, 'organization:') ? substr($source, 13) : null;
            $out[] = ['key'=>$key, 'override_value'=>$directValid ? $direct['value'] : null,
                'effective_value'=>$this->resolve($key,$ctx),
                'source'=>['type'=>$sourceId === null ? 'SYSTEM' : 'ORGANIZATION','organization_id'=>$sourceId],
                'inherited'=>!$directValid || $source !== $ctx['context']];
        }
        return $out;
    }

    /** @return array{class:string,key:string,context:string} */
    private function coordinates(string $key, string $context): array
    {
        return ['class'=>'Display','key'=>DisplaySettingRules::storageKey($key),'context'=>$context];
    }

    /** @return array{exists:bool,value:mixed} */
    private function override(string $key, string $context): array
    {
        $result = $this->db->table('settings')->select('value,type')->where($this->coordinates($key,$context))->get();
        if ($result === false) throw new RuntimeException('Display Settings read failed');
        $row = $result->getRowArray();
        if ($row === null) return ['exists'=>false,'value'=>null];
        $value = (string) $row['value'];
        $parsed = match ((string) $row['type']) {
            'boolean'=>$value === '1', 'integer'=>(int) $value, 'double'=>(float) $value, default=>$value,
        };
        return ['exists'=>true,'value'=>$parsed];
    }

    private function valueType(mixed $value): string
    {
        return is_bool($value) ? 'boolean' : (is_int($value) ? 'integer' : 'string');
    }

    private function encode(mixed $value): string
    {
        return is_bool($value) ? ($value ? '1' : '0') : (string) $value;
    }

    /** @param callable():void $callback */
    private function transaction(callable $callback): void
    {
        if (!$this->db->transBegin()) throw new RuntimeException('Display Settings transaction start failed');
        try {
            $callback();
            if (!$this->db->transStatus() || !$this->db->transCommit()) throw new RuntimeException('Display Settings transaction commit failed');
        } catch (Throwable $e) {
            $this->db->transRollback();
            $this->db->resetTransStatus();
            throw $e;
        }
    }
}
