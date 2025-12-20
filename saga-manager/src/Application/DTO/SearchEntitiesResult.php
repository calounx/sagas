<?php
declare(strict_types=1);

namespace SagaManager\Application\DTO;

/**
 * Search Entities Result DTO
 *
 * Paginated collection of entity DTOs.
 */
final readonly class SearchEntitiesResult
{
    /**
     * @param EntityDTO[] $entities
     */
    public function __construct(
        public array $entities,
        public int $total,
        public int $limit,
        public int $offset
    ) {
    }

    /**
     * Convert to array for JSON serialization
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'entities' => array_map(fn(EntityDTO $dto) => $dto->toArray(), $this->entities),
            'total' => $this->total,
            'limit' => $this->limit,
            'offset' => $this->offset,
            'has_more' => $this->offset + count($this->entities) < $this->total,
        ];
    }
}
