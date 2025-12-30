<?php
declare(strict_types=1);

namespace SagaManager\Infrastructure\Container;

use SagaManager\Application\Service\ApplicationServiceProvider;
use SagaManager\Application\Service\CommandBus;
use SagaManager\Application\Service\QueryBus;
use SagaManager\Domain\Repository\EntityRepositoryInterface;
use SagaManager\Domain\Repository\RelationshipRepositoryInterface;
use SagaManager\Domain\Repository\ContentFragmentRepositoryInterface;
use SagaManager\Domain\Repository\AttributeDefinitionRepositoryInterface;
use SagaManager\Domain\Repository\AttributeValueRepositoryInterface;
use SagaManager\Domain\Repository\TimelineEventRepositoryInterface;
use SagaManager\Domain\Repository\QualityMetricsRepositoryInterface;
use SagaManager\Infrastructure\Repository\MariaDBEntityRepository;
use SagaManager\Infrastructure\Repository\MariaDBRelationshipRepository;
use SagaManager\Infrastructure\Repository\MariaDBContentFragmentRepository;
use SagaManager\Infrastructure\Repository\MariaDBAttributeDefinitionRepository;
use SagaManager\Infrastructure\Repository\MariaDBAttributeValueRepository;
use SagaManager\Infrastructure\Repository\MariaDBTimelineEventRepository;
use SagaManager\Infrastructure\Repository\MariaDBQualityMetricsRepository;
use SagaManager\Infrastructure\WordPress\SagaEntityPostType;
use SagaManager\Infrastructure\WordPress\SagaEntityMetaBox;
use SagaManager\Infrastructure\WordPress\SagaTypeTaxonomy;

/**
 * Service Container
 *
 * Simple dependency injection container for the Saga Manager plugin.
 * Manages singleton instances and wires dependencies.
 */
final class ServiceContainer
{
    private static ?self $instance = null;

    /** @var array<class-string, object> */
    private array $instances = [];

    /** @var array<class-string, callable> */
    private array $factories = [];

    private function __construct()
    {
        $this->registerDefaults();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Reset the container (useful for testing)
     */
    public static function reset(): void
    {
        self::$instance = null;
    }

    /**
     * Get a service by its interface/class name
     *
     * @template T of object
     * @param class-string<T> $id
     * @return T
     */
    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (isset($this->factories[$id])) {
            $this->instances[$id] = ($this->factories[$id])($this);
            return $this->instances[$id];
        }

        throw new \RuntimeException(sprintf('Service not found: %s', $id));
    }

    /**
     * Check if a service exists
     *
     * @param class-string $id
     */
    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || isset($this->factories[$id]);
    }

    /**
     * Register a factory for a service
     *
     * @template T of object
     * @param class-string<T> $id
     * @param callable(ServiceContainer): T $factory
     */
    public function register(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        // Clear cached instance if exists
        unset($this->instances[$id]);
    }

    /**
     * Register a singleton instance
     *
     * @template T of object
     * @param class-string<T> $id
     * @param T $instance
     */
    public function set(string $id, object $instance): void
    {
        $this->instances[$id] = $instance;
    }

    /**
     * Register default services
     */
    private function registerDefaults(): void
    {
        // Repository interfaces mapped to implementations
        $this->register(
            EntityRepositoryInterface::class,
            fn() => new MariaDBEntityRepository()
        );

        $this->register(
            RelationshipRepositoryInterface::class,
            fn() => new MariaDBRelationshipRepository()
        );

        $this->register(
            ContentFragmentRepositoryInterface::class,
            fn() => new MariaDBContentFragmentRepository()
        );

        $this->register(
            AttributeDefinitionRepositoryInterface::class,
            fn() => new MariaDBAttributeDefinitionRepository()
        );

        $this->register(
            AttributeValueRepositoryInterface::class,
            fn() => new MariaDBAttributeValueRepository()
        );

        $this->register(
            TimelineEventRepositoryInterface::class,
            fn() => new MariaDBTimelineEventRepository()
        );

        $this->register(
            QualityMetricsRepositoryInterface::class,
            fn() => new MariaDBQualityMetricsRepository()
        );

        // Application Service Provider (configures CommandBus/QueryBus)
        $this->register(
            ApplicationServiceProvider::class,
            fn(ServiceContainer $c) => new ApplicationServiceProvider(
                $c->get(EntityRepositoryInterface::class),
                $c->get(RelationshipRepositoryInterface::class)
            )
        );

        // Command Bus (via ApplicationServiceProvider)
        $this->register(
            CommandBus::class,
            fn(ServiceContainer $c) => $c->get(ApplicationServiceProvider::class)->getCommandBus()
        );

        // Query Bus (via ApplicationServiceProvider)
        $this->register(
            QueryBus::class,
            fn(ServiceContainer $c) => $c->get(ApplicationServiceProvider::class)->getQueryBus()
        );

        // WordPress Custom Post Type
        $this->register(
            SagaEntityPostType::class,
            fn(ServiceContainer $c) => new SagaEntityPostType(
                $c->get(EntityRepositoryInterface::class)
            )
        );

        // WordPress Meta Box
        $this->register(
            SagaEntityMetaBox::class,
            fn() => new SagaEntityMetaBox()
        );

        // WordPress Taxonomy
        $this->register(
            SagaTypeTaxonomy::class,
            fn() => new SagaTypeTaxonomy()
        );
    }
}
