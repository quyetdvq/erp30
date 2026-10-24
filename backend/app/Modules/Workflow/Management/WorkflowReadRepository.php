<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Management;

use CodeIgniter\Database\BaseConnection;
use UnexpectedValueException;

/** Workflow and Lifecycle catalog SQL; relation rows are fetched only after parent PBAC. */
final class WorkflowReadRepository
{
    public function __construct(private readonly BaseConnection $db) {}

    /** @return list<array<string,mixed>> */
    public function all(string $type): array
    {
        return $this->readRows($type, null);
    }

    /** Bounded SQL sample for the M3.3 readiness command, not API pagination. */
    public function sample(string $type, int $limit): array
    {
        if ($limit < 1 || $limit > 100) {
            throw new \InvalidArgumentException('Sample size must be 1..100.');
        }
        return $this->readRows($type, $limit);
    }

    /** @return list<array<string,mixed>> */
    private function readRows(string $type, ?int $limit): array
    {
        $table = WorkflowReadContract::table($type);
        $query = $this->db->table($table)->orderBy('id', 'ASC');
        if ($limit !== null) {
            $query->limit($limit);
        }
        return $this->many($query->get(), 'Workflow list: ' . $type);
    }

    /** @return array<string,mixed>|null */
    public function one(string $type, string $id): ?array
    {
        $table = WorkflowReadContract::table($type);
        return $this->first($this->db->table($table)->where('id', $id)->get(), 'Workflow detail: ' . $type);
    }

    /** @param array<string,mixed> $parent @return list<array<string,mixed>> */
    public function related(string $relation, array $parent): array
    {
        $id = (string) $parent['id'];
        if ($relation === 'states') {
            $builder = $this->db->table('lifecycle_states')
                ->where('lifecycle_id', $id)->orderBy('sequence', 'ASC')->orderBy('id', 'ASC');
        } elseif ($relation === 'transitions') {
            $builder = $this->db->table('lifecycle_transitions')
                ->where('lifecycle_id', $id)->orderBy('from_state_id', 'ASC')
                ->orderBy('action_code', 'ASC')->orderBy('id', 'ASC');
        } else {
            throw new \InvalidArgumentException('Invalid Workflow relation.');
        }
        return $this->many($builder->get(), 'Workflow relation: ' . $relation);
    }

    /** @return list<array<string,mixed>> */
    private function many(mixed $result, string $where): array
    {
        if ($result === false) {
            $error = $this->db->error();
            throw new UnexpectedValueException($where . ' SQL failed: ' . ($error['message'] ?? 'unknown'));
        }
        return $result->getResultArray();
    }

    /** @return array<string,mixed>|null */
    private function first(mixed $result, string $where): ?array
    {
        if ($result === false) {
            $error = $this->db->error();
            throw new UnexpectedValueException($where . ' SQL failed: ' . ($error['message'] ?? 'unknown'));
        }
        return $result->getRowArray();
    }
}
