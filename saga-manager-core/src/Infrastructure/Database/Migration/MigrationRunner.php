<?php
declare(strict_types=1);

namespace SagaManagerCore\Infrastructure\Database\Migration;

use SagaManagerCore\Infrastructure\Database\Port\DatabaseConnectionInterface;
use SagaManagerCore\Infrastructure\Database\Port\MigrationRunnerInterface;
use SagaManagerCore\Infrastructure\Database\Exception\MigrationException;

/**
 * Migration Runner Implementation
 *
 * Manages database schema migrations with version tracking,
 * batch execution, and rollback support.
 *
 * @package SagaManagerCore\Infrastructure\Database\Migration
 */
class MigrationRunner implements MigrationRunnerInterface
{
    private const MIGRATIONS_TABLE = 'migrations';

    private DatabaseConnectionInterface $connection;
    /** @var array<MigrationInterface> */
    private array $migrations = [];
    private string $migrationsPath = '';

    public function __construct(DatabaseConnectionInterface $connection)
    {
        $this->connection = $connection;
    }

    public function migrate(bool $pretend = false): array
    {
        $this->ensureMigrationsTable();

        $pending = $this->getPending();
        $batch = $this->getNextBatchNumber();
        $ran = [];

        foreach ($pending as $migration) {
            if ($pretend) {
                $ran[] = $migration->getName();
                continue;
            }

            try {
                $this->runMigration($migration, $batch);
                $ran[] = $migration->getName();
            } catch (\Throwable $e) {
                throw MigrationException::migrationFailed(
                    $migration->getName(),
                    $e->getMessage(),
                    $e
                );
            }
        }

        return $ran;
    }

    public function run(MigrationInterface $migration, bool $pretend = false): void
    {
        $this->ensureMigrationsTable();

        if ($this->hasRun($migration->getName())) {
            throw MigrationException::migrationAlreadyRan($migration->getName());
        }

        if ($pretend) {
            return;
        }

        $batch = $this->getNextBatchNumber();
        $this->runMigration($migration, $batch);
    }

    public function rollback(int $steps = 1, bool $pretend = false): array
    {
        $this->ensureMigrationsTable();

        $completed = $this->getCompletedByBatch($steps);
        $rolledBack = [];

        foreach (array_reverse($completed) as $record) {
            $migration = $this->findMigration($record['migration']);

            if ($migration === null) {
                throw MigrationException::migrationNotFound($record['migration']);
            }

            if ($pretend) {
                $rolledBack[] = $migration->getName();
                continue;
            }

            try {
                $this->rollbackMigration($migration, $record['migration']);
                $rolledBack[] = $migration->getName();
            } catch (\Throwable $e) {
                throw MigrationException::rollbackFailed(
                    $migration->getName(),
                    $e->getMessage(),
                    $e
                );
            }
        }

        return $rolledBack;
    }

    public function reset(bool $pretend = false): array
    {
        $this->ensureMigrationsTable();

        $completed = $this->getCompleted();
        $rolledBack = [];

        // Get all completed in reverse order
        $records = $this->connection->query()
            ->from(self::MIGRATIONS_TABLE)
            ->orderBy('batch', 'DESC')
            ->orderBy('id', 'DESC')
            ->get();

        foreach ($records as $record) {
            $migration = $this->findMigration($record['migration']);

            if ($migration === null) {
                continue;
            }

            if ($pretend) {
                $rolledBack[] = $migration->getName();
                continue;
            }

            try {
                $this->rollbackMigration($migration, $record['migration']);
                $rolledBack[] = $migration->getName();
            } catch (\Throwable $e) {
                throw MigrationException::rollbackFailed(
                    $migration->getName(),
                    $e->getMessage(),
                    $e
                );
            }
        }

        return $rolledBack;
    }

    public function refresh(bool $pretend = false): array
    {
        $this->reset($pretend);
        return $this->migrate($pretend);
    }

    public function status(): array
    {
        $this->ensureMigrationsTable();

        $completed = $this->getCompletedWithDetails();
        $status = [];

        foreach ($this->migrations as $migration) {
            $name = $migration->getName();
            $record = $completed[$name] ?? null;

            $status[] = [
                'name' => $name,
                'batch' => $record['batch'] ?? null,
                'ran' => $record !== null,
                'ran_at' => $record['created_at'] ?? null,
            ];
        }

        return $status;
    }

    public function getPending(): array
    {
        $completed = $this->getCompleted();

        return array_filter(
            $this->migrations,
            fn(MigrationInterface $m) => !in_array($m->getName(), $completed, true)
        );
    }

    public function getCompleted(): array
    {
        if (!$this->hasMigrationsTable()) {
            return [];
        }

        return $this->connection->query()
            ->from(self::MIGRATIONS_TABLE)
            ->pluck('migration');
    }

    public function hasPending(): bool
    {
        return count($this->getPending()) > 0;
    }

    public function register(MigrationInterface $migration): void
    {
        $this->migrations[$migration->getName()] = $migration;

        // Sort by version
        uasort($this->migrations, fn($a, $b) => $a->getVersion() <=> $b->getVersion());
    }

    public function registerAll(array $migrations): void
    {
        foreach ($migrations as $migration) {
            $this->register($migration);
        }
    }

    public function getCurrentVersion(): string
    {
        if (!$this->hasMigrationsTable()) {
            return '0';
        }

        $last = $this->connection->query()
            ->from(self::MIGRATIONS_TABLE)
            ->orderBy('batch', 'DESC')
            ->orderBy('id', 'DESC')
            ->first();

        if ($last === null) {
            return '0';
        }

        $migration = $this->findMigration($last['migration']);
        return $migration?->getVersion() ?? '0';
    }

    public function getLatestVersion(): string
    {
        if (empty($this->migrations)) {
            return '0';
        }

        $last = end($this->migrations);
        return $last->getVersion();
    }

    public function getNextBatchNumber(): int
    {
        if (!$this->hasMigrationsTable()) {
            return 1;
        }

        $max = $this->connection->query()
            ->from(self::MIGRATIONS_TABLE)
            ->max('batch');

        return ((int) $max) + 1;
    }

    public function createMigrationsTable(): void
    {
        if ($this->hasMigrationsTable()) {
            return;
        }

        $this->connection->schema()->createTable(
            self::MIGRATIONS_TABLE,
            "id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
             migration VARCHAR(255) NOT NULL,
             batch INT UNSIGNED NOT NULL,
             created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
             UNIQUE KEY uk_migration (migration)"
        );
    }

    public function hasMigrationsTable(): bool
    {
        return $this->connection->schema()->tableExists(self::MIGRATIONS_TABLE);
    }

    public function preview(): array
    {
        $preview = [];
        $pending = $this->getPending();

        foreach ($pending as $migration) {
            // This would require capturing SQL statements
            // For now, just return migration names
            $preview[$migration->getName()] = [
                'description' => $migration->getDescription(),
            ];
        }

        return $preview;
    }

    public function setMigrationsPath(string $path): void
    {
        $this->migrationsPath = $path;
    }

    public function loadMigrations(): void
    {
        if (empty($this->migrationsPath) || !is_dir($this->migrationsPath)) {
            return;
        }

        $files = glob($this->migrationsPath . '/*.php');

        foreach ($files as $file) {
            $className = $this->getMigrationClassFromFile($file);

            if ($className !== null && class_exists($className)) {
                $migration = new $className();

                if ($migration instanceof MigrationInterface) {
                    $this->register($migration);
                }
            }
        }
    }

    public function generate(string $name, ?string $table = null, bool $create = true): string
    {
        if (empty($this->migrationsPath)) {
            throw MigrationException::generateFailed($name, 'Migrations path not set');
        }

        if (!is_dir($this->migrationsPath)) {
            mkdir($this->migrationsPath, 0755, true);
        }

        $timestamp = date('Y_m_d_His');
        $className = $this->getClassName($name);
        $fileName = "{$timestamp}_{$name}.php";
        $filePath = $this->migrationsPath . '/' . $fileName;

        $content = $this->generateMigrationContent($className, $table, $create);

        if (file_put_contents($filePath, $content) === false) {
            throw MigrationException::generateFailed($name, 'Failed to write file');
        }

        return $filePath;
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    private function ensureMigrationsTable(): void
    {
        if (!$this->hasMigrationsTable()) {
            $this->createMigrationsTable();
        }
    }

    private function runMigration(MigrationInterface $migration, int $batch): void
    {
        $this->connection->transaction()->run(function () use ($migration, $batch) {
            $migration->up($this->connection->schema());

            $this->connection->query()
                ->table(self::MIGRATIONS_TABLE)
                ->insert([
                    'migration' => $migration->getName(),
                    'batch' => $batch,
                ]);
        });

        error_log(sprintf('[SAGA][MIGRATION] Ran: %s', $migration->getName()));
    }

    private function rollbackMigration(MigrationInterface $migration, string $name): void
    {
        $this->connection->transaction()->run(function () use ($migration, $name) {
            $migration->down($this->connection->schema());

            $this->connection->query()
                ->table(self::MIGRATIONS_TABLE)
                ->where('migration', '=', $name)
                ->delete();
        });

        error_log(sprintf('[SAGA][MIGRATION] Rolled back: %s', $migration->getName()));
    }

    private function hasRun(string $name): bool
    {
        return in_array($name, $this->getCompleted(), true);
    }

    private function findMigration(string $name): ?MigrationInterface
    {
        return $this->migrations[$name] ?? null;
    }

    /**
     * @return array<array{migration: string, batch: int}>
     */
    private function getCompletedByBatch(int $batches): array
    {
        $maxBatch = $this->connection->query()
            ->from(self::MIGRATIONS_TABLE)
            ->max('batch');

        $minBatch = max(1, ((int) $maxBatch) - $batches + 1);

        return $this->connection->query()
            ->from(self::MIGRATIONS_TABLE)
            ->where('batch', '>=', $minBatch)
            ->orderBy('batch', 'DESC')
            ->orderBy('id', 'DESC')
            ->get();
    }

    /**
     * @return array<string, array{batch: int, created_at: string}>
     */
    private function getCompletedWithDetails(): array
    {
        if (!$this->hasMigrationsTable()) {
            return [];
        }

        $records = $this->connection->query()
            ->from(self::MIGRATIONS_TABLE)
            ->get();

        $result = [];
        foreach ($records as $record) {
            $result[$record['migration']] = [
                'batch' => $record['batch'],
                'created_at' => $record['created_at'],
            ];
        }

        return $result;
    }

    private function getMigrationClassFromFile(string $file): ?string
    {
        $content = file_get_contents($file);

        if (preg_match('/namespace\s+([^;]+);/', $content, $nsMatch)) {
            $namespace = $nsMatch[1];
        } else {
            $namespace = '';
        }

        if (preg_match('/class\s+(\w+)/', $content, $classMatch)) {
            $className = $classMatch[1];
            return $namespace ? "{$namespace}\\{$className}" : $className;
        }

        return null;
    }

    private function getClassName(string $name): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
    }

    private function generateMigrationContent(string $className, ?string $table, bool $create): string
    {
        $upContent = $create && $table
            ? "\$schema->createTable('{$table}', \"id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY\");"
            : "// Add migration logic here";

        $downContent = $create && $table
            ? "\$schema->dropTable('{$table}');"
            : "// Revert migration logic here";

        return <<<PHP
<?php
declare(strict_types=1);

namespace SagaManagerCore\Infrastructure\Database\Migrations;

use SagaManagerCore\Infrastructure\Database\Migration\AbstractMigration;
use SagaManagerCore\Infrastructure\Database\Port\SchemaManagerInterface;

class {$className} extends AbstractMigration
{
    public function up(SchemaManagerInterface \$schema): void
    {
        {$upContent}
    }

    public function down(SchemaManagerInterface \$schema): void
    {
        {$downContent}
    }

    public function getDescription(): string
    {
        return '{$className}';
    }
}
PHP;
    }
}
