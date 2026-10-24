<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Mutation;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/** Fixed-table SQL repository for M3.3B4. Transaction ownership is in service. */
final class WorkflowMutationRepository
{
    private const MASTERS = [
        'workflow_state' => 'workflow_states',
        'lifecycle' => 'lifecycles',
    ];

    public function __construct(private readonly BaseConnection $db) {}

    /** @return array<string,mixed>|null */
    public function master(string $type, string $id, bool $lock = false): ?array
    {
        $table = self::MASTERS[$type] ?? throw new \InvalidArgumentException('Unknown Workflow master.');
        $builder = $this->db->table($table)->where('id', $id);
        return $this->one($lock ? $this->db->query($builder->getCompiledSelect() . ' FOR UPDATE') : $builder->get(), $table);
    }

    /** @return array<string,mixed>|null */
    public function byCode(string $type, string $code): ?array
    {
        $table = self::MASTERS[$type] ?? throw new \InvalidArgumentException('Unknown Workflow master.');
        return $this->one($this->db->table($table)->where('code', $code)->get(), $table . '.code');
    }

    /** @param array<string,mixed> $row */
    public function createMaster(string $type, array $row): string
    {
        $table = self::MASTERS[$type] ?? throw new \InvalidArgumentException('Unknown Workflow master.');
        $this->must($this->db->table($table)->insert($row), $table . ' insert');
        return WorkflowMutationRules::id((string) $this->db->insertID(), 'id');
    }

    /** @param array<string,mixed> $row */
    public function updateMaster(string $type, string $id, array $row): void
    {
        $table = self::MASTERS[$type] ?? throw new \InvalidArgumentException('Unknown Workflow master.');
        $this->must($this->db->table($table)->where('id', $id)->update($row), $table . ' update');
    }

    /** @return list<array<string,mixed>> */
    public function states(string $lifecycleId): array
    {
        return $this->many($this->db->table('lifecycle_states')->where('lifecycle_id', $lifecycleId)
            ->orderBy('sequence', 'ASC')->orderBy('id', 'ASC')->get(), 'lifecycle states');
    }

    /** @return list<array<string,mixed>> */
    public function transitions(string $lifecycleId): array
    {
        return $this->many($this->db->table('lifecycle_transitions')->where('lifecycle_id', $lifecycleId)
            ->orderBy('from_state_id', 'ASC')->orderBy('action_code', 'ASC')->orderBy('id', 'ASC')->get(), 'lifecycle transitions');
    }

    public function activeState(string $id): bool
    {
        // Configuration runs inside the Lifecycle transaction. Lock each
        // referenced ACTIVE master State too so concurrent deactivation cannot
        // sneak between validation and the membership/transition INSERT.
        $query = $this->db->table('workflow_states')->select('id')->where('id', $id)
            ->where('status', 'ACTIVE');
        return $this->one($this->db->query($query->getCompiledSelect() . ' FOR UPDATE'),
            'Workflow State lookup') !== null;
    }

    /** @param array<string,mixed> $row */
    public function insertState(array $row): void
    {
        $this->must($this->db->table('lifecycle_states')->insert($row), 'lifecycle_states insert');
    }

    /** @param array<string,mixed> $row */
    public function insertTransition(array $row): void
    {
        $this->must($this->db->table('lifecycle_transitions')->insert($row), 'lifecycle_transitions insert');
    }

    /** @return array<string,mixed>|null */
    private function one(mixed $result, string $name): ?array
    {
        if ($result === false) {
            throw new RuntimeException('Workflow SQL failed: ' . $name);
        }
        return $result->getRowArray();
    }

    /** @return list<array<string,mixed>> */
    private function many(mixed $result, string $name): array
    {
        if ($result === false) {
            throw new RuntimeException('Workflow SQL failed: ' . $name);
        }
        return $result->getResultArray();
    }

    private function must(mixed $ok, string $name): void
    {
        if ($ok !== true) {
            throw new RuntimeException('Workflow SQL write failed: ' . $name);
        }
    }
}
