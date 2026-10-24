<?php

declare(strict_types=1);

namespace App\Modules\Workflow\Management;

/** Fixed Workflow-owned read targets; client input can never supply SQL identifiers. */
final class WorkflowReadContract
{
    public const TABLES = [
        'workflow_state' => 'workflow_states',
        'lifecycle' => 'lifecycles',
    ];

    public static function table(string $type): string
    {
        return self::TABLES[$type] ?? throw new \InvalidArgumentException('Unsupported Workflow read resource.');
    }
}
