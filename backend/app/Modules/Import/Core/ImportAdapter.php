<?php

declare(strict_types=1);

namespace App\Modules\Import\Core;

/** Module boundary: adding another import type never modifies ImportPipeline. */
interface ImportAdapter
{
    public function entityType(): string;

    /** @return array{entity_type:string,version:string,max_rows:int,max_bytes:int,record_types:list<string>,columns:list<string>} */
    public function template(): array;

    public function authorizeStart(): void;

    /** @return list<string> Business tables which MUST support rollback. */
    public function transactionalTables(): array;

    /** @return list<string> Authorized native ImportJob statuses for this module. */
    public function viewableJobStatuses(\App\Modules\Access\UnifiedAccessService $access): array;

    /** @param array<string,mixed> $row */
    public function canViewJob(\App\Modules\Access\UnifiedAccessService $access, array $row): bool;

    /** @param array<string,string|null> $row @param array<string,array{id:string,code:string}> $sourceMap
     *  @return array{target_ref_id:?string,entity_key:?string}
     */
    public function execute(array $row, string $mode, array &$sourceMap): array;
}
