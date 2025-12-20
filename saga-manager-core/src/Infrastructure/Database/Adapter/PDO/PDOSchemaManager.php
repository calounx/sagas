<?php
declare(strict_types=1);

namespace SagaManagerCore\Infrastructure\Database\Adapter\PDO;

use SagaManagerCore\Infrastructure\Database\Port\SchemaManagerInterface;
use SagaManagerCore\Infrastructure\Database\Exception\SchemaException;

/**
 * PDO Schema Manager Implementation
 *
 * Provides DDL operations for PDO connections (MySQL/MariaDB/SQLite).
 *
 * @package SagaManagerCore\Infrastructure\Database\Adapter\PDO
 */
class PDOSchemaManager implements SchemaManagerInterface
{
    private PDOConnection $connection;

    public function __construct(PDOConnection $connection)
    {
        $this->connection = $connection;
    }

    public function createTable(string $table, string $definition, array $options = []): bool
    {
        $fullTable = $this->connection->getFullTableName($table);
        $engine = $options['engine'] ?? 'InnoDB';
        $charset = $options['charset'] ?? 'utf8mb4';
        $collate = $options['collate'] ?? 'utf8mb4_unicode_ci';

        $sql = sprintf(
            'CREATE TABLE IF NOT EXISTS %s (%s) ENGINE=%s DEFAULT CHARSET=%s COLLATE=%s',
            $fullTable,
            $definition,
            $engine,
            $charset,
            $collate
        );

        try {
            $this->connection->getPdo()->exec($sql);
            return true;
        } catch (\PDOException $e) {
            throw SchemaException::createTableFailed($table, $e->getMessage());
        }
    }

    public function createTableRaw(string $sql): bool
    {
        try {
            $this->connection->getPdo()->exec($sql);
            return true;
        } catch (\PDOException $e) {
            throw SchemaException::ddlFailed($sql, $e->getMessage());
        }
    }

    public function dropTable(string $table): void
    {
        $fullTable = $this->connection->getFullTableName($table);

        try {
            $this->connection->getPdo()->exec("DROP TABLE IF EXISTS {$fullTable}");
        } catch (\PDOException $e) {
            throw SchemaException::dropTableFailed($table, $e->getMessage());
        }
    }

    public function dropTables(array $tables): void
    {
        $this->connection->getPdo()->exec('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($tables as $table) {
            $this->dropTable($table);
        }

        $this->connection->getPdo()->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function tableExists(string $table): bool
    {
        $fullTable = $this->connection->getFullTableName($table);
        $database = $this->connection->getDatabaseName();

        $stmt = $this->connection->getPdo()->prepare(
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?"
        );
        $stmt->execute([$database, $fullTable]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function addColumn(string $table, string $column, string $definition, ?string $after = null): void
    {
        $fullTable = $this->connection->getFullTableName($table);
        $afterClause = $after ? " AFTER `{$after}`" : '';

        $sql = sprintf(
            'ALTER TABLE %s ADD COLUMN `%s` %s%s',
            $fullTable,
            $column,
            $definition,
            $afterClause
        );

        try {
            $this->connection->getPdo()->exec($sql);
        } catch (\PDOException $e) {
            throw SchemaException::alterTableFailed($table, $e->getMessage());
        }
    }

    public function modifyColumn(string $table, string $column, string $definition): void
    {
        $fullTable = $this->connection->getFullTableName($table);

        $sql = sprintf(
            'ALTER TABLE %s MODIFY COLUMN `%s` %s',
            $fullTable,
            $column,
            $definition
        );

        try {
            $this->connection->getPdo()->exec($sql);
        } catch (\PDOException $e) {
            throw SchemaException::alterTableFailed($table, $e->getMessage());
        }
    }

    public function dropColumn(string $table, string $column): void
    {
        $fullTable = $this->connection->getFullTableName($table);

        try {
            $this->connection->getPdo()->exec(
                "ALTER TABLE {$fullTable} DROP COLUMN `{$column}`"
            );
        } catch (\PDOException $e) {
            throw SchemaException::alterTableFailed($table, $e->getMessage());
        }
    }

    public function columnExists(string $table, string $column): bool
    {
        $fullTable = $this->connection->getFullTableName($table);
        $database = $this->connection->getDatabaseName();

        $stmt = $this->connection->getPdo()->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?"
        );
        $stmt->execute([$database, $fullTable, $column]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function addIndex(string $table, string $name, string|array $columns, string $type = 'INDEX'): void
    {
        $fullTable = $this->connection->getFullTableName($table);
        $columnList = is_array($columns) ? implode('`, `', $columns) : $columns;

        $sql = sprintf(
            'CREATE %s %s ON %s (`%s`)',
            $type,
            $name,
            $fullTable,
            $columnList
        );

        try {
            $this->connection->getPdo()->exec($sql);
        } catch (\PDOException $e) {
            throw SchemaException::alterTableFailed($table, $e->getMessage());
        }
    }

    public function dropIndex(string $table, string $name): void
    {
        $fullTable = $this->connection->getFullTableName($table);

        try {
            $this->connection->getPdo()->exec("DROP INDEX {$name} ON {$fullTable}");
        } catch (\PDOException $e) {
            throw SchemaException::alterTableFailed($table, $e->getMessage());
        }
    }

    public function indexExists(string $table, string $name): bool
    {
        $fullTable = $this->connection->getFullTableName($table);
        $database = $this->connection->getDatabaseName();

        $stmt = $this->connection->getPdo()->prepare(
            "SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?"
        );
        $stmt->execute([$database, $fullTable, $name]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function addForeignKey(
        string $table,
        string $name,
        string $column,
        string $referenceTable,
        string $referenceColumn,
        string $onDelete = 'CASCADE',
        string $onUpdate = 'CASCADE'
    ): void {
        $fullTable = $this->connection->getFullTableName($table);
        $fullRefTable = $this->connection->getFullTableName($referenceTable);

        $sql = sprintf(
            'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (`%s`) REFERENCES %s(`%s`) ON DELETE %s ON UPDATE %s',
            $fullTable,
            $name,
            $column,
            $fullRefTable,
            $referenceColumn,
            $onDelete,
            $onUpdate
        );

        try {
            $this->connection->getPdo()->exec($sql);
        } catch (\PDOException $e) {
            throw SchemaException::foreignKeyFailed($table, $name, $e->getMessage());
        }
    }

    public function dropForeignKey(string $table, string $name): void
    {
        $fullTable = $this->connection->getFullTableName($table);

        try {
            $this->connection->getPdo()->exec(
                "ALTER TABLE {$fullTable} DROP FOREIGN KEY {$name}"
            );
        } catch (\PDOException $e) {
            throw SchemaException::alterTableFailed($table, $e->getMessage());
        }
    }

    public function foreignKeyExists(string $table, string $name): bool
    {
        $fullTable = $this->connection->getFullTableName($table);
        $database = $this->connection->getDatabaseName();

        $stmt = $this->connection->getPdo()->prepare(
            "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'"
        );
        $stmt->execute([$database, $fullTable, $name]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function getTables(): array
    {
        $prefix = $this->connection->getSagaTablePrefix();
        $database = $this->connection->getDatabaseName();

        $stmt = $this->connection->getPdo()->prepare(
            "SELECT TABLE_NAME FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME LIKE ?"
        );
        $stmt->execute([$database, $prefix . '%']);

        $tables = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $tables[] = str_replace($prefix, '', $row['TABLE_NAME']);
        }

        return $tables;
    }

    public function getColumns(string $table): array
    {
        $fullTable = $this->connection->getFullTableName($table);
        $database = $this->connection->getDatabaseName();

        $stmt = $this->connection->getPdo()->prepare(
            "SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_KEY, COLUMN_DEFAULT, EXTRA
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?"
        );
        $stmt->execute([$database, $fullTable]);

        $columns = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $columns[$row['COLUMN_NAME']] = [
                'name' => $row['COLUMN_NAME'],
                'type' => $row['DATA_TYPE'],
                'null' => $row['IS_NULLABLE'] === 'YES',
                'key' => $row['COLUMN_KEY'],
                'default' => $row['COLUMN_DEFAULT'],
                'extra' => $row['EXTRA'],
            ];
        }

        return $columns;
    }

    public function getIndexes(string $table): array
    {
        $fullTable = $this->connection->getFullTableName($table);
        $database = $this->connection->getDatabaseName();

        $stmt = $this->connection->getPdo()->prepare(
            "SELECT INDEX_NAME, COLUMN_NAME, NON_UNIQUE, INDEX_TYPE
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
             ORDER BY INDEX_NAME, SEQ_IN_INDEX"
        );
        $stmt->execute([$database, $fullTable]);

        $indexes = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $name = $row['INDEX_NAME'];
            if (!isset($indexes[$name])) {
                $indexes[$name] = [
                    'name' => $name,
                    'columns' => [],
                    'unique' => $row['NON_UNIQUE'] === '0',
                    'type' => $row['INDEX_TYPE'],
                ];
            }
            $indexes[$name]['columns'][] = $row['COLUMN_NAME'];
        }

        return $indexes;
    }

    public function getSchemaVersion(): string
    {
        // Would need a version table implementation
        return '1.0.0';
    }

    public function setSchemaVersion(string $version): void
    {
        // Would need a version table implementation
    }

    public function migrate(): array
    {
        return [];
    }

    public function rollbackMigration(): array
    {
        return [];
    }

    public function getCharsetCollate(): string
    {
        return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
    }
}
