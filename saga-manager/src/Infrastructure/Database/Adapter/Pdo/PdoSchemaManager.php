<?php

declare(strict_types=1);

namespace SagaManager\Infrastructure\Database\Adapter\Pdo;

use SagaManager\Infrastructure\Database\Contract\SchemaManagerInterface;
use SagaManager\Domain\Exception\DatabaseException;

/**
 * PDO Schema Manager
 *
 * Manages database schema operations for PDO connections.
 * Supports MySQL/MariaDB, PostgreSQL, and SQLite.
 *
 * @example
 *   $schema = $db->schema();
 *   $schema->createTable('entities', [
 *       'id' => ['type' => 'bigint', 'unsigned' => true, 'autoincrement' => true],
 *       'name' => ['type' => 'varchar', 'length' => 255],
 *   ]);
 */
final class PdoSchemaManager implements SchemaManagerInterface
{
    public function __construct(
        private readonly PdoConnection $connection,
        private readonly string $tablePrefix = '',
    ) {}

    public function tableExists(string $table): bool
    {
        $tableName = $this->tablePrefix . $table;
        $driver = $this->connection->getDriverName();

        try {
            $sql = match ($driver) {
                'mysql' => "SHOW TABLES LIKE ?",
                'pgsql' => "SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = ?)",
                'sqlite' => "SELECT name FROM sqlite_master WHERE type='table' AND name=?",
                default => throw new DatabaseException("Unsupported driver: {$driver}"),
            };

            $stmt = $this->connection->execute($sql, [$tableName]);
            return $stmt->fetch() !== false;

        } catch (\PDOException $e) {
            throw new DatabaseException(
                'Failed to check table existence: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }

    public function createTable(string $table, array $columns, array $options = []): void
    {
        $tableName = $this->tablePrefix . $table;
        $driver = $this->connection->getDriverName();

        $columnDefs = [];
        foreach ($columns as $name => $definition) {
            $columnDefs[] = $this->buildColumnDefinition($name, $definition, $driver);
        }

        // Primary key
        if (isset($options['primary'])) {
            $pk = implode(', ', (array) $options['primary']);
            $columnDefs[] = "PRIMARY KEY ({$pk})";
        }

        // Unique constraints
        if (isset($options['unique'])) {
            foreach ($options['unique'] as $name => $cols) {
                $colList = implode(', ', (array) $cols);
                $columnDefs[] = "CONSTRAINT {$name} UNIQUE ({$colList})";
            }
        }

        $sql = "CREATE TABLE " . $this->connection->quoteIdentifier($tableName) . " (\n    ";
        $sql .= implode(",\n    ", $columnDefs);
        $sql .= "\n)";

        // Engine and charset for MySQL
        if ($driver === 'mysql') {
            $engine = $options['engine'] ?? 'InnoDB';
            $charset = $options['charset'] ?? 'utf8mb4';
            $collation = $options['collation'] ?? 'utf8mb4_unicode_ci';
            $sql .= " ENGINE={$engine} DEFAULT CHARSET={$charset} COLLATE={$collation}";
        }

        $this->raw($sql);

        // Create indexes
        if (isset($options['indexes'])) {
            foreach ($options['indexes'] as $name => $cols) {
                $this->addIndex($table, $name, (array) $cols);
            }
        }
    }

    public function dropTable(string $table): void
    {
        $tableName = $this->tablePrefix . $table;
        $this->raw("DROP TABLE " . $this->connection->quoteIdentifier($tableName));
    }

    public function dropTableIfExists(string $table): void
    {
        $tableName = $this->tablePrefix . $table;
        $this->raw("DROP TABLE IF EXISTS " . $this->connection->quoteIdentifier($tableName));
    }

    public function renameTable(string $from, string $to): void
    {
        $fromName = $this->tablePrefix . $from;
        $toName = $this->tablePrefix . $to;
        $driver = $this->connection->getDriverName();

        $sql = match ($driver) {
            'mysql' => "RENAME TABLE " . $this->connection->quoteIdentifier($fromName) .
                       " TO " . $this->connection->quoteIdentifier($toName),
            'pgsql', 'sqlite' => "ALTER TABLE " . $this->connection->quoteIdentifier($fromName) .
                                 " RENAME TO " . $this->connection->quoteIdentifier($toName),
            default => throw new DatabaseException("Unsupported driver: {$driver}"),
        };

        $this->raw($sql);
    }

    public function columnExists(string $table, string $column): bool
    {
        $columns = $this->getColumns($table);
        return isset($columns[$column]);
    }

    public function addColumn(string $table, string $column, array $definition): void
    {
        $tableName = $this->tablePrefix . $table;
        $driver = $this->connection->getDriverName();

        $columnDef = $this->buildColumnDefinition($column, $definition, $driver);

        $sql = "ALTER TABLE " . $this->connection->quoteIdentifier($tableName) .
               " ADD COLUMN " . $columnDef;

        // Handle AFTER clause for MySQL
        if ($driver === 'mysql' && isset($definition['after'])) {
            $sql .= " AFTER " . $this->connection->quoteIdentifier($definition['after']);
        }

        $this->raw($sql);
    }

    public function modifyColumn(string $table, string $column, array $definition): void
    {
        $tableName = $this->tablePrefix . $table;
        $driver = $this->connection->getDriverName();

        $columnDef = $this->buildColumnDefinition($column, $definition, $driver);

        $sql = match ($driver) {
            'mysql' => "ALTER TABLE " . $this->connection->quoteIdentifier($tableName) .
                       " MODIFY COLUMN " . $columnDef,
            'pgsql' => $this->buildPostgresModifyColumn($tableName, $column, $definition),
            'sqlite' => throw new DatabaseException('SQLite does not support modifying columns'),
            default => throw new DatabaseException("Unsupported driver: {$driver}"),
        };

        $this->raw($sql);
    }

    public function dropColumn(string $table, string $column): void
    {
        $tableName = $this->tablePrefix . $table;
        $this->raw(
            "ALTER TABLE " . $this->connection->quoteIdentifier($tableName) .
            " DROP COLUMN " . $this->connection->quoteIdentifier($column)
        );
    }

    public function renameColumn(string $table, string $from, string $to): void
    {
        $tableName = $this->tablePrefix . $table;
        $driver = $this->connection->getDriverName();

        $sql = match ($driver) {
            'mysql' => "ALTER TABLE " . $this->connection->quoteIdentifier($tableName) .
                       " RENAME COLUMN " . $this->connection->quoteIdentifier($from) .
                       " TO " . $this->connection->quoteIdentifier($to),
            'pgsql' => "ALTER TABLE " . $this->connection->quoteIdentifier($tableName) .
                       " RENAME COLUMN " . $this->connection->quoteIdentifier($from) .
                       " TO " . $this->connection->quoteIdentifier($to),
            'sqlite' => "ALTER TABLE " . $this->connection->quoteIdentifier($tableName) .
                        " RENAME COLUMN " . $this->connection->quoteIdentifier($from) .
                        " TO " . $this->connection->quoteIdentifier($to),
            default => throw new DatabaseException("Unsupported driver: {$driver}"),
        };

        $this->raw($sql);
    }

    public function addIndex(string $table, string $name, array $columns, string $type = 'INDEX'): void
    {
        $tableName = $this->tablePrefix . $table;
        $columnList = implode(', ', array_map(
            fn($col) => $this->connection->quoteIdentifier($col),
            $columns
        ));

        $indexType = match (strtoupper($type)) {
            'UNIQUE' => 'UNIQUE INDEX',
            'FULLTEXT' => 'FULLTEXT INDEX',
            'SPATIAL' => 'SPATIAL INDEX',
            default => 'INDEX',
        };

        $sql = "CREATE {$indexType} {$name} ON " .
               $this->connection->quoteIdentifier($tableName) .
               " ({$columnList})";

        $this->raw($sql);
    }

    public function dropIndex(string $table, string $name): void
    {
        $tableName = $this->tablePrefix . $table;
        $driver = $this->connection->getDriverName();

        $sql = match ($driver) {
            'mysql' => "DROP INDEX {$name} ON " . $this->connection->quoteIdentifier($tableName),
            'pgsql', 'sqlite' => "DROP INDEX {$name}",
            default => throw new DatabaseException("Unsupported driver: {$driver}"),
        };

        $this->raw($sql);
    }

    public function indexExists(string $table, string $name): bool
    {
        $indexes = $this->getIndexes($table);
        return isset($indexes[$name]);
    }

    public function addForeignKey(
        string $table,
        string $name,
        array $columns,
        string $referencedTable,
        array $referencedColumns,
        string $onDelete = 'CASCADE',
        string $onUpdate = 'CASCADE'
    ): void {
        $tableName = $this->tablePrefix . $table;
        $refTableName = $this->tablePrefix . $referencedTable;

        $columnList = implode(', ', array_map(
            fn($col) => $this->connection->quoteIdentifier($col),
            $columns
        ));

        $refColumnList = implode(', ', array_map(
            fn($col) => $this->connection->quoteIdentifier($col),
            $referencedColumns
        ));

        $sql = "ALTER TABLE " . $this->connection->quoteIdentifier($tableName) .
               " ADD CONSTRAINT {$name} FOREIGN KEY ({$columnList})" .
               " REFERENCES " . $this->connection->quoteIdentifier($refTableName) .
               " ({$refColumnList})" .
               " ON DELETE {$onDelete}" .
               " ON UPDATE {$onUpdate}";

        $this->raw($sql);
    }

    public function dropForeignKey(string $table, string $name): void
    {
        $tableName = $this->tablePrefix . $table;
        $driver = $this->connection->getDriverName();

        $sql = match ($driver) {
            'mysql' => "ALTER TABLE " . $this->connection->quoteIdentifier($tableName) .
                       " DROP FOREIGN KEY {$name}",
            'pgsql' => "ALTER TABLE " . $this->connection->quoteIdentifier($tableName) .
                       " DROP CONSTRAINT {$name}",
            'sqlite' => throw new DatabaseException('SQLite does not support dropping foreign keys'),
            default => throw new DatabaseException("Unsupported driver: {$driver}"),
        };

        $this->raw($sql);
    }

    public function getColumns(string $table): array
    {
        $tableName = $this->tablePrefix . $table;
        $driver = $this->connection->getDriverName();

        $sql = match ($driver) {
            'mysql' => "SHOW COLUMNS FROM " . $this->connection->quoteIdentifier($tableName),
            'pgsql' => "SELECT column_name, data_type, is_nullable, column_default
                        FROM information_schema.columns
                        WHERE table_name = ?",
            'sqlite' => "PRAGMA table_info(" . $this->connection->quoteIdentifier($tableName) . ")",
            default => throw new DatabaseException("Unsupported driver: {$driver}"),
        };

        $params = $driver === 'pgsql' ? [$tableName] : [];
        $stmt = $this->connection->execute($sql, $params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $columns = [];
        foreach ($rows as $row) {
            $name = match ($driver) {
                'mysql' => $row['Field'],
                'pgsql' => $row['column_name'],
                'sqlite' => $row['name'],
            };
            $columns[$name] = $row;
        }

        return $columns;
    }

    public function getIndexes(string $table): array
    {
        $tableName = $this->tablePrefix . $table;
        $driver = $this->connection->getDriverName();

        $sql = match ($driver) {
            'mysql' => "SHOW INDEX FROM " . $this->connection->quoteIdentifier($tableName),
            'pgsql' => "SELECT indexname, indexdef FROM pg_indexes WHERE tablename = ?",
            'sqlite' => "PRAGMA index_list(" . $this->connection->quoteIdentifier($tableName) . ")",
            default => throw new DatabaseException("Unsupported driver: {$driver}"),
        };

        $params = $driver === 'pgsql' ? [$tableName] : [];
        $stmt = $this->connection->execute($sql, $params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $indexes = [];
        foreach ($rows as $row) {
            $name = match ($driver) {
                'mysql' => $row['Key_name'],
                'pgsql' => $row['indexname'],
                'sqlite' => $row['name'],
            };
            $indexes[$name] = $row;
        }

        return $indexes;
    }

    public function truncate(string $table): void
    {
        $tableName = $this->tablePrefix . $table;
        $driver = $this->connection->getDriverName();

        $sql = match ($driver) {
            'sqlite' => "DELETE FROM " . $this->connection->quoteIdentifier($tableName),
            default => "TRUNCATE TABLE " . $this->connection->quoteIdentifier($tableName),
        };

        $this->raw($sql);

        // Reset autoincrement for SQLite
        if ($driver === 'sqlite') {
            $this->raw("DELETE FROM sqlite_sequence WHERE name = ?", [$tableName]);
        }
    }

    public function raw(string $sql, array $params = []): void
    {
        try {
            $this->connection->execute($sql, $params);
        } catch (\PDOException $e) {
            throw new DatabaseException(
                'Schema operation failed: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Build column definition SQL
     *
     * @param string $name Column name
     * @param array<string, mixed> $definition Column definition
     * @param string $driver Database driver
     * @return string
     */
    private function buildColumnDefinition(string $name, array $definition, string $driver): string
    {
        $type = $this->mapColumnType($definition['type'] ?? 'varchar', $driver, $definition);
        $sql = $this->connection->quoteIdentifier($name) . ' ' . $type;

        // Handle UNSIGNED for MySQL
        if ($driver === 'mysql' && ($definition['unsigned'] ?? false)) {
            $sql .= ' UNSIGNED';
        }

        // Nullable
        if (!($definition['nullable'] ?? true)) {
            $sql .= ' NOT NULL';
        }

        // Auto increment
        if ($definition['autoincrement'] ?? false) {
            $sql .= match ($driver) {
                'mysql' => ' AUTO_INCREMENT',
                'pgsql' => '', // Handled by SERIAL type
                'sqlite' => '', // Handled by INTEGER PRIMARY KEY
                default => '',
            };
        }

        // Default value
        if (array_key_exists('default', $definition)) {
            $default = $definition['default'];
            if ($default === null) {
                $sql .= ' DEFAULT NULL';
            } elseif ($default === 'CURRENT_TIMESTAMP') {
                $sql .= ' DEFAULT CURRENT_TIMESTAMP';
            } elseif (is_bool($default)) {
                $sql .= ' DEFAULT ' . ($default ? 'TRUE' : 'FALSE');
            } elseif (is_numeric($default)) {
                $sql .= ' DEFAULT ' . $default;
            } else {
                $sql .= ' DEFAULT ' . $this->connection->quote((string) $default);
            }
        }

        return $sql;
    }

    /**
     * Map abstract column type to database-specific type
     *
     * @param string $type Abstract type
     * @param string $driver Database driver
     * @param array<string, mixed> $definition Column definition
     * @return string
     */
    private function mapColumnType(string $type, string $driver, array $definition): string
    {
        $length = $definition['length'] ?? null;
        $precision = $definition['precision'] ?? null;
        $scale = $definition['scale'] ?? null;

        return match ($type) {
            'bigint' => match ($driver) {
                'pgsql' => ($definition['autoincrement'] ?? false) ? 'BIGSERIAL' : 'BIGINT',
                default => 'BIGINT',
            },
            'int', 'integer' => match ($driver) {
                'pgsql' => ($definition['autoincrement'] ?? false) ? 'SERIAL' : 'INTEGER',
                default => 'INT',
            },
            'smallint' => 'SMALLINT',
            'tinyint' => match ($driver) {
                'pgsql' => 'SMALLINT',
                default => 'TINYINT',
            },
            'float' => 'FLOAT',
            'double' => match ($driver) {
                'pgsql' => 'DOUBLE PRECISION',
                default => 'DOUBLE',
            },
            'decimal' => sprintf('DECIMAL(%d,%d)', $precision ?? 10, $scale ?? 2),
            'varchar' => 'VARCHAR(' . ($length ?? 255) . ')',
            'char' => 'CHAR(' . ($length ?? 1) . ')',
            'text' => 'TEXT',
            'mediumtext' => match ($driver) {
                'mysql' => 'MEDIUMTEXT',
                default => 'TEXT',
            },
            'longtext' => match ($driver) {
                'mysql' => 'LONGTEXT',
                default => 'TEXT',
            },
            'blob' => 'BLOB',
            'boolean', 'bool' => match ($driver) {
                'mysql' => 'TINYINT(1)',
                default => 'BOOLEAN',
            },
            'date' => 'DATE',
            'datetime' => match ($driver) {
                'pgsql' => 'TIMESTAMP',
                default => 'DATETIME',
            },
            'timestamp' => 'TIMESTAMP',
            'time' => 'TIME',
            'json' => match ($driver) {
                'mysql' => 'JSON',
                'pgsql' => 'JSONB',
                default => 'TEXT',
            },
            'uuid' => match ($driver) {
                'pgsql' => 'UUID',
                default => 'CHAR(36)',
            },
            'enum' => match ($driver) {
                'mysql' => 'ENUM(' . implode(',', array_map(
                    fn($v) => $this->connection->quote($v),
                    $definition['values'] ?? []
                )) . ')',
                default => 'VARCHAR(255)',
            },
            default => $type,
        };
    }

    /**
     * Build PostgreSQL column modification statement
     *
     * @param string $tableName Full table name
     * @param string $column Column name
     * @param array<string, mixed> $definition Column definition
     * @return string
     */
    private function buildPostgresModifyColumn(string $tableName, string $column, array $definition): string
    {
        $statements = [];
        $quotedColumn = $this->connection->quoteIdentifier($column);
        $quotedTable = $this->connection->quoteIdentifier($tableName);

        // Type change
        if (isset($definition['type'])) {
            $type = $this->mapColumnType($definition['type'], 'pgsql', $definition);
            $statements[] = "ALTER TABLE {$quotedTable} ALTER COLUMN {$quotedColumn} TYPE {$type}";
        }

        // Nullable change
        if (isset($definition['nullable'])) {
            $action = $definition['nullable'] ? 'DROP NOT NULL' : 'SET NOT NULL';
            $statements[] = "ALTER TABLE {$quotedTable} ALTER COLUMN {$quotedColumn} {$action}";
        }

        // Default change
        if (array_key_exists('default', $definition)) {
            if ($definition['default'] === null) {
                $statements[] = "ALTER TABLE {$quotedTable} ALTER COLUMN {$quotedColumn} DROP DEFAULT";
            } else {
                $default = is_string($definition['default']) && $definition['default'] !== 'CURRENT_TIMESTAMP'
                    ? $this->connection->quote($definition['default'])
                    : $definition['default'];
                $statements[] = "ALTER TABLE {$quotedTable} ALTER COLUMN {$quotedColumn} SET DEFAULT {$default}";
            }
        }

        return implode('; ', $statements);
    }
}
