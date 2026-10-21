<?php

declare(strict_types=1);

/**
 * 03C-02 narrow scratch-only attestation and exact-PK cleanup executor.
 * All mutable SQL is hardcoded to five tables and rows carrying this canary's
 * validated marker/FK. An empty scratch baseline is mandatory.
 */
final class ScratchNumberingPilotAudit
{
    public const LEASE = 'dinkerp-03c02-scratch-pilot';

    /** @var array<string,array{column:string,type:string}> */
    private const MARKERS = [
        'dinktech_auto_numbering_counters' => ['column' => 'numbering_series_id', 'type' => 'i'],
        'dinktech_auto_numbering_series' => ['column' => 'series_code', 'type' => 's'],
        'dinktech_organizations' => ['column' => 'code', 'type' => 's'],
        'dinktech_products' => ['column' => 'sku', 'type' => 's'],
        'dinktech_resources' => ['column' => 'name', 'type' => 's'],
    ];

    private bool $leaseHeld = false;

    /** @param array<string,mixed> $reference */
    public function __construct(private readonly mysqli $db, private readonly array $reference)
    {
    }

    public function acquire(): void
    {
        $this->identityAndGrants();
        $lock = $this->rows('SELECT GET_LOCK(\'' . self::LEASE . '\', 0) AS acquired');
        if (count($lock) !== 1 || (int) ($lock[0]['acquired'] ?? 0) !== 1) {
            throw new RuntimeException('03C-02 STOP: scratch runner lease is held by another process.');
        }
        $this->leaseHeld = true;
    }

    public function release(): void
    {
        if ($this->leaseHeld) {
            $this->rows('SELECT RELEASE_LOCK(\'' . self::LEASE . '\') AS released');
            $this->leaseHeld = false;
        }
    }

    /** @return array<string,int> */
    public function attestAndCounts(): array
    {
        if (! $this->leaseHeld) {
            throw new RuntimeException('03C-02 STOP: no scratch lease.');
        }
        $metadata = [
            'tables' => $this->rows('SELECT TABLE_NAME, ENGINE, TABLE_TYPE, AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME'),
            'primary_keys' => $this->rows("SELECT TABLE_NAME, COLUMN_NAME, ORDINAL_POSITION FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'PRIMARY' ORDER BY TABLE_NAME, ORDINAL_POSITION"),
            'foreign_keys' => $this->rows('SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME, ORDINAL_POSITION FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION'),
            'foreign_key_rules' => $this->rows('SELECT TABLE_NAME, CONSTRAINT_NAME, UPDATE_RULE, DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() ORDER BY TABLE_NAME, CONSTRAINT_NAME'),
        ];
        ScratchReadinessGate::assertMetadataParity($this->reference, $metadata);
        ScratchReadinessGate::assertSchemaFingerprint([
            'tables' => $this->rows('SELECT TABLE_NAME, TABLE_TYPE, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME'),
            'columns' => $this->rows('SELECT TABLE_NAME, COLUMN_NAME, ORDINAL_POSITION, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME, ORDINAL_POSITION'),
            'indices' => $this->rows('SELECT TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX, COLUMN_NAME, NON_UNIQUE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX'),
            'constraints' => $this->rows('SELECT TABLE_NAME, CONSTRAINT_NAME, CONSTRAINT_TYPE FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME, CONSTRAINT_NAME'),
        ]);
        ScratchNumberingPilotPolicy::assertRelationalDeletionOrder($metadata);
        $counts = [];
        foreach ($metadata['tables'] as $table) {
            $name = $table['TABLE_NAME'] ?? null;
            if (! is_string($name) || preg_match('/\Adinktech_[a-z0-9_]{1,54}\z/D', $name) !== 1
                || ($table['ENGINE'] ?? null) !== 'InnoDB' || ($table['TABLE_TYPE'] ?? null) !== 'BASE TABLE') {
                throw new RuntimeException('03C-02 STOP: unsafe scratch table name or engine.');
            }
            $rows = $this->rows('SELECT COUNT(*) AS n FROM `' . $name . '`');
            $count = $rows[0]['n'] ?? null;
            if (count($rows) !== 1 || ! is_scalar($count) || ! ctype_digit((string) $count)
                || (float) $count > PHP_INT_MAX) {
                throw new RuntimeException('03C-02 STOP: invalid scratch count.');
            }
            $counts[$name] = (int) $count;
        }
        ksort($counts, SORT_STRING);
        return $counts;
    }

    /**
     * @param array<string,list<array{id:int,marker:int|string}>> $ledger
     * @param list<string> $allTables
     */
    public function cleanupExactOwnedRows(array $ledger, array $allTables, int $migrationRows): void
    {
        // Take no action at all until EVERY table has exactly the expected
        // number of rows and EVERY owned PK still bears its own marker/FK.
        $counts = $this->attestAndCounts();
        $idLedger = [];
        foreach ($ledger as $table => $rows) {
            if (! isset(self::MARKERS[$table]) || ! is_array($rows) || ! array_is_list($rows)) {
                throw new RuntimeException('03C-02 STOP: unapproved cleanup ledger.');
            }
            foreach ($rows as $row) {
                if (! is_array($row) || ! isset($row['id'], $row['marker'])
                    || ! is_int($row['id']) || $row['id'] <= 0
                    || (! is_string($row['marker']) && ! is_int($row['marker']))) {
                    throw new RuntimeException('03C-02 STOP: invalid cleanup PK/marker.');
                }
                $idLedger[$table][] = $row['id'];
                $this->assertMarker($table, $row['id'], $row['marker']);
            }
        }
        ScratchNumberingPilotPolicy::assertOwnedInventory($counts, $idLedger, $allTables, $migrationRows);

        if (! $this->db->begin_transaction()) {
            throw new RuntimeException('03C-02 STOP: cannot start exact-row cleanup transaction.');
        }
        try {
            // Repeat all ownership checks INSIDE the cleanup transaction.
            ScratchNumberingPilotPolicy::assertOwnedInventory(
                $this->attestAndCounts(), $idLedger, $allTables, $migrationRows,
            );
            foreach ($ledger as $table => $rows) {
                foreach ($rows as $row) {
                    $this->assertMarker($table, $row['id'], $row['marker']);
                }
            }
            foreach (ScratchNumberingPilotPolicy::DELETION_ORDER as $table) {
                foreach ($ledger[$table] ?? [] as $row) {
                    $this->deleteExactlyOne($table, $row['id'], $row['marker']);
                }
            }
            $clean = $this->attestAndCounts();
            if (ScratchReadinessGate::assertCleanScratch($clean, $allTables) !== $migrationRows) {
                throw new RuntimeException('03C-02 STOP: migration history changed during cleanup.');
            }
            if (! $this->db->commit()) {
                throw new RuntimeException('03C-02 STOP: exact-row cleanup commit failed.');
            }
        } catch (Throwable $error) {
            $this->db->rollback();
            throw $error;
        }
        $after = $this->attestAndCounts();
        if (ScratchReadinessGate::assertCleanScratch($after, $allTables) !== $migrationRows) {
            throw new RuntimeException('03C-02 STOP: scratch not empty after committed cleanup.');
        }
    }

    /** @return list<array<string,mixed>> */
    public function rows(string $sql): array
    {
        // Only internal fixed read queries; not a general-purpose SQL runner.
        $result = $this->db->query($sql);
        if (! $result instanceof mysqli_result) {
            throw new RuntimeException('03C-02 STOP: read-only audit query failed.');
        }
        try {
            return $result->fetch_all(MYSQLI_ASSOC);
        } finally {
            $result->free();
        }
    }

    /** @return array{id:int,numbering_series_id:int,last_value:int}|null */
    public function counterForSeries(int $seriesId): ?array
    {
        $statement = $this->db->prepare('SELECT id, numbering_series_id, last_value FROM `dinktech_auto_numbering_counters` WHERE numbering_series_id = ?');
        if (! $statement) {
            throw new RuntimeException('03C-02 STOP: counter proof preparation failed.');
        }
        try {
            $statement->bind_param('i', $seriesId);
            if (! $statement->execute()) {
                throw new RuntimeException('03C-02 STOP: counter ownership query failed.');
            }
            $rows = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
            if (count($rows) > 1) {
                throw new RuntimeException('03C-02 STOP: unexpected multiple counters on owned series.');
            }
            return $rows === [] ? null : [
                'id' => (int) $rows[0]['id'],
                'numbering_series_id' => (int) $rows[0]['numbering_series_id'],
                'last_value' => (int) $rows[0]['last_value'],
            ];
        } finally {
            $statement->close();
        }
    }

    /**
     * Recover the one committed canary Product if allocation committed before
     * the caller had a chance to register the returned insert ID.
     * @return int|null
     */
    public function issuedProductBySku(string $sku, string $expectedCode): ?int
    {
        if (! $this->leaseHeld || preg_match('/\AS03C02-ISSUED-[A-F0-9]{16}\z/D', $sku) !== 1
            || $expectedCode !== '002') {
            throw new RuntimeException('03C-02 STOP: invalid Product recovery marker.');
        }
        $statement = $this->db->prepare('SELECT id, code FROM `dinktech_products` WHERE sku = ?');
        if (! $statement) {
            throw new RuntimeException('03C-02 STOP: cannot prepare owned Product lookup.');
        }
        try {
            $statement->bind_param('s', $sku);
            if (! $statement->execute()) {
                throw new RuntimeException('03C-02 STOP: owned Product lookup failed.');
            }
            $result = $statement->get_result();
            if (! $result instanceof mysqli_result) {
                throw new RuntimeException('03C-02 STOP: owned Product lookup has no result.');
            }
            $rows = $result->fetch_all(MYSQLI_ASSOC);
            if ($rows === []) {
                return null;
            }
            if (count($rows) !== 1 || (string) $rows[0]['code'] !== $expectedCode
                || (int) $rows[0]['id'] < 1) {
                throw new RuntimeException('03C-02 STOP: committed Product identity is not proven.');
            }
            return (int) $rows[0]['id'];
        } finally {
            $statement->close();
        }
    }

    private function assertMarker(string $table, int $id, int|string $marker): void
    {
        $column = self::MARKERS[$table]['column'];
        $type = self::MARKERS[$table]['type'];
        $statement = $this->db->prepare('SELECT `' . $column . '` AS marker FROM `' . $table . '` WHERE id = ?');
        if (! $statement) {
            throw new RuntimeException('03C-02 STOP: cannot query PK ownership.');
        }
        try {
            $statement->bind_param('i', $id);
            if (! $statement->execute()) {
                throw new RuntimeException('03C-02 STOP: ownership read failed.');
            }
            $result = $statement->get_result();
            $rows = $result->fetch_all(MYSQLI_ASSOC);
            if (count($rows) !== 1 || ($type === 'i'
                ? (int) ($rows[0]['marker'] ?? -1) !== $marker
                : (string) ($rows[0]['marker'] ?? '') !== $marker)) {
                throw new RuntimeException('03C-02 STOP: owned row marker/FK changed.');
            }
        } finally {
            $statement->close();
        }
    }

    private function deleteExactlyOne(string $table, int $id, int|string $marker): void
    {
        $column = self::MARKERS[$table]['column'];
        $type = self::MARKERS[$table]['type'];
        $statement = $this->db->prepare('DELETE FROM `' . $table . '` WHERE id = ? AND `' . $column . '` = ?');
        if (! $statement) {
            throw new RuntimeException('03C-02 STOP: exact-PK delete preparation failed.');
        }
        try {
            if ($type === 'i') {
                $statement->bind_param('ii', $id, $marker);
            } else {
                $statement->bind_param('is', $id, $marker);
            }
            if (! $statement->execute() || $statement->affected_rows !== 1) {
                throw new RuntimeException('03C-02 STOP: exact-PK cleanup did not delete exactly one owned row.');
            }
        } finally {
            $statement->close();
        }
    }

    private function identityAndGrants(): void
    {
        $identity = $this->rows('SELECT CURRENT_USER() AS account, CURRENT_ROLE() AS role_name, DATABASE() AS db_name, @@port AS port');
        if (count($identity) !== 1 || ($identity[0]['db_name'] ?? null) !== ScratchReadinessGate::DATABASE
            || (int) ($identity[0]['port'] ?? 0) !== 3306) {
            throw new RuntimeException('03C-02 STOP: wrong scratch connection target.');
        }
        $grants = [];
        foreach ($this->rows('SHOW GRANTS') as $row) {
            $grant = reset($row);
            if (! is_string($grant)) {
                throw new RuntimeException('03C-02 STOP: malformed scratch grant.');
            }
            $grants[] = $grant;
        }
        ScratchReadinessGate::assertDmlOnlyGrants(
            $grants,
            (string) ($identity[0]['account'] ?? ''),
            ($identity[0]['role_name'] ?? null) === null ? null : (string) $identity[0]['role_name'],
        );
    }
}
