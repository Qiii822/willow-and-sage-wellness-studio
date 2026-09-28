<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Converts the database and every table to utf8mb4.
 *
 * The migrations never set a charset, so each table inherits whatever the
 * database was created with. On a database created as latin1 (or utf8mb3),
 * synced text containing characters outside that set - an emoji in a booking
 * note, for example - fails to insert with MySQL error 1366 "Incorrect string
 * value", and the whole sync rolls back.
 *
 * Every table is moved to the same collation, not just the ones that failed.
 * Bookings are joined to customers on `square_customer_id`, and MySQL refuses
 * to compare columns with different collations (error 1267).
 *
 * Foreign keys are dropped and re-created around the conversion. MySQL will
 * not change the charset of a column that a foreign key uses (error 1832),
 * even with FOREIGN_KEY_CHECKS off on some versions.
 *
 * Skipped:
 * - `sessions`: its `id` is deliberately `ascii_bin` (case-sensitive), which is
 *   what CakePHP's database session storage expects. It has no foreign keys.
 * - the migration log tables, which this migration is itself recorded in.
 *
 * Safe to re-run: tables already in the target collation are left alone, so a
 * run that stopped part-way can simply be repeated.
 */
class FixBookingDate extends BaseMigration
{
    private const CHARSET = 'utf8mb4';

    private const COLLATION = 'utf8mb4_unicode_ci';

    /** Tables whose collation is intentional. */
    private const SKIP_TABLES = ['sessions'];

    /**
     * @return void
     */
    public function up(): void
    {
        $adapter = $this->getAdapter();

        // Charsets are a MySQL/MariaDB concept - nothing to do on SQLite etc.
        if ($adapter === null || $adapter->getAdapterType() !== 'mysql') {
            return;
        }

        $row = $this->fetchRow('SELECT DATABASE() AS db_name');
        $database = (string)($row['db_name'] ?? '');

        if ($database === '') {
            return;
        }

        // Only affects tables created from now on; existing ones are done below.
        $this->execute(sprintf(
            'ALTER DATABASE %s CHARACTER SET %s COLLATE %s',
            $this->quote($database),
            self::CHARSET,
            self::COLLATION,
        ));

        $tables = $this->tablesToConvert();

        if ($tables === []) {
            return;
        }

        $constraints = $this->foreignKeysTouching($tables);

        // Nothing is validated when a key is re-added with checks off, which
        // is what we want: the data was already valid before the conversion.
        $this->execute('SET FOREIGN_KEY_CHECKS = 0');

        $failure = null;

        try {
            foreach ($constraints as $constraint) {
                $this->execute(sprintf(
                    'ALTER TABLE %s DROP FOREIGN KEY %s',
                    $this->quote($constraint['table']),
                    $this->quote($constraint['name']),
                ));
            }

            foreach ($tables as $table) {
                $this->execute(sprintf(
                    'ALTER TABLE %s CONVERT TO CHARACTER SET %s COLLATE %s',
                    $this->quote($table),
                    self::CHARSET,
                    self::COLLATION,
                ));
            }
        } catch (\Throwable $e) {
            $failure = $e;
        }

        // Always put the keys back - even after a failure - so an error part-way
        // never leaves the schema without its constraints. Keys already dropped
        // are re-added; ones never dropped are skipped by the try/catch.
        foreach ($constraints as $constraint) {
            try {
                $this->execute(sprintf(
                    'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (%s) ON UPDATE %s ON DELETE %s',
                    $this->quote($constraint['table']),
                    $this->quote($constraint['name']),
                    implode(', ', array_map([$this, 'quote'], $constraint['columns'])),
                    $this->quote($constraint['ref_table']),
                    implode(', ', array_map([$this, 'quote'], $constraint['ref_columns'])),
                    $constraint['on_update'],
                    $constraint['on_delete'],
                ));
            } catch (\Throwable $e) {
                $failure ??= $e;
            }
        }

        $this->execute('SET FOREIGN_KEY_CHECKS = 1');

        if ($failure !== null) {
            throw $failure;
        }
    }

    /**
     * Not reversible: the previous charset differed per environment and is not
     * recorded, and narrowing utf8mb4 back down would corrupt any emoji stored
     * since.
     *
     * @return void
     */
    public function down(): void
    {
    }

    /**
     * Base tables in this database that are not yet fully utf8mb4_unicode_ci.
     *
     * @return array<string>
     */
    private function tablesToConvert(): array
    {
        $rows = $this->fetchAll(
            'SELECT t.TABLE_NAME AS table_name '
            . 'FROM information_schema.TABLES t '
            . 'WHERE t.TABLE_SCHEMA = DATABASE() '
            . "AND t.TABLE_TYPE = 'BASE TABLE' "
            . "AND t.TABLE_NAME NOT LIKE '%phinxlog' "
            . "AND (t.TABLE_COLLATION <> '" . self::COLLATION . "' "
            . 'OR EXISTS ('
            . 'SELECT 1 FROM information_schema.COLUMNS c '
            . 'WHERE c.TABLE_SCHEMA = t.TABLE_SCHEMA '
            . 'AND c.TABLE_NAME = t.TABLE_NAME '
            . 'AND c.COLLATION_NAME IS NOT NULL '
            . "AND c.COLLATION_NAME <> '" . self::COLLATION . "'"
            . '))',
        );

        $tables = [];

        foreach ($rows as $row) {
            $name = (string)$row['table_name'];

            if (!in_array($name, self::SKIP_TABLES, true)) {
                $tables[] = $name;
            }
        }

        return $tables;
    }

    /**
     * Every foreign key that sits on, or points at, one of the given tables.
     *
     * @param array<string> $tables
     * @return array<string, array{table: string, name: string, columns: array<string>, ref_table: string, ref_columns: array<string>, on_update: string, on_delete: string}>
     */
    private function foreignKeysTouching(array $tables): array
    {
        $rows = $this->fetchAll(
            'SELECT k.TABLE_NAME AS table_name, k.CONSTRAINT_NAME AS constraint_name, '
            . 'k.COLUMN_NAME AS column_name, k.REFERENCED_TABLE_NAME AS ref_table, '
            . 'k.REFERENCED_COLUMN_NAME AS ref_column, r.UPDATE_RULE AS update_rule, '
            . 'r.DELETE_RULE AS delete_rule '
            . 'FROM information_schema.KEY_COLUMN_USAGE k '
            . 'JOIN information_schema.REFERENTIAL_CONSTRAINTS r '
            . 'ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA '
            . 'AND r.TABLE_NAME = k.TABLE_NAME '
            . 'AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME '
            . 'WHERE k.TABLE_SCHEMA = DATABASE() '
            . 'AND k.REFERENCED_TABLE_NAME IS NOT NULL '
            . 'ORDER BY k.TABLE_NAME, k.CONSTRAINT_NAME, k.ORDINAL_POSITION',
        );

        $constraints = [];

        foreach ($rows as $row) {
            $key = $row['table_name'] . '.' . $row['constraint_name'];

            $constraints[$key] ??= [
                'table' => (string)$row['table_name'],
                'name' => (string)$row['constraint_name'],
                'columns' => [],
                'ref_table' => (string)$row['ref_table'],
                'ref_columns' => [],
                'on_update' => (string)$row['update_rule'],
                'on_delete' => (string)$row['delete_rule'],
            ];

            $constraints[$key]['columns'][] = (string)$row['column_name'];
            $constraints[$key]['ref_columns'][] = (string)$row['ref_column'];
        }

        return array_filter(
            $constraints,
            fn (array $c): bool => in_array($c['table'], $tables, true)
                || in_array($c['ref_table'], $tables, true),
        );
    }

    /**
     * Quotes an identifier for use in a statement.
     *
     * @param string $identifier
     * @return string
     */
    private function quote(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
