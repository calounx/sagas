<?php
declare(strict_types=1);

namespace SagaManager\Domain\Entity;

use SagaManager\Domain\Exception\ValidationException;

/**
 * Timeline Event Entity
 *
 * Represents a significant event in a saga's timeline.
 * Events have both a canonical date (saga format) and a normalized timestamp for sorting.
 */
class TimelineEvent
{
    private ?TimelineEventId $id;
    private SagaId $sagaId;
    private ?EntityId $eventEntityId;
    private CanonDate $canonDate;
    private NormalizedTimestamp $normalizedTimestamp;
    private string $title;
    private ?string $description;
    /** @var int[] */
    private array $participants;
    /** @var int[] */
    private array $locations;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    /**
     * @param int[] $participants Entity IDs of participants
     * @param int[] $locations Entity IDs of locations
     */
    public function __construct(
        SagaId $sagaId,
        CanonDate $canonDate,
        NormalizedTimestamp $normalizedTimestamp,
        string $title,
        ?string $description = null,
        array $participants = [],
        array $locations = [],
        ?EntityId $eventEntityId = null,
        ?TimelineEventId $id = null,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null
    ) {
        $this->validateTitle($title);

        $this->id = $id;
        $this->sagaId = $sagaId;
        $this->eventEntityId = $eventEntityId;
        $this->canonDate = $canonDate;
        $this->normalizedTimestamp = $normalizedTimestamp;
        $this->title = $title;
        $this->description = $description;
        $this->participants = array_values(array_unique($participants));
        $this->locations = array_values(array_unique($locations));
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new \DateTimeImmutable();
    }

    private function validateTitle(string $title): void
    {
        if (trim($title) === '') {
            throw new ValidationException('Timeline event title cannot be empty');
        }

        if (mb_strlen($title) > 255) {
            throw new ValidationException('Timeline event title cannot exceed 255 characters');
        }
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?TimelineEventId
    {
        return $this->id;
    }

    public function setId(TimelineEventId $id): void
    {
        if ($this->id !== null) {
            throw new ValidationException('Timeline event ID cannot be changed once set');
        }

        $this->id = $id;
    }

    public function getSagaId(): SagaId
    {
        return $this->sagaId;
    }

    public function getEventEntityId(): ?EntityId
    {
        return $this->eventEntityId;
    }

    public function setEventEntityId(?EntityId $entityId): void
    {
        $this->eventEntityId = $entityId;
        $this->touch();
    }

    public function getCanonDate(): CanonDate
    {
        return $this->canonDate;
    }

    public function getNormalizedTimestamp(): NormalizedTimestamp
    {
        return $this->normalizedTimestamp;
    }

    public function updateDate(CanonDate $canonDate, NormalizedTimestamp $normalizedTimestamp): void
    {
        $this->canonDate = $canonDate;
        $this->normalizedTimestamp = $normalizedTimestamp;
        $this->touch();
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function updateTitle(string $title): void
    {
        $this->validateTitle($title);
        $this->title = $title;
        $this->touch();
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
        $this->touch();
    }

    /**
     * @return int[]
     */
    public function getParticipants(): array
    {
        return $this->participants;
    }

    /**
     * @param int[] $participants
     */
    public function setParticipants(array $participants): void
    {
        $this->participants = array_values(array_unique($participants));
        $this->touch();
    }

    public function addParticipant(int $entityId): void
    {
        if (!in_array($entityId, $this->participants, true)) {
            $this->participants[] = $entityId;
            $this->touch();
        }
    }

    public function removeParticipant(int $entityId): void
    {
        $this->participants = array_values(array_filter(
            $this->participants,
            fn($id) => $id !== $entityId
        ));
        $this->touch();
    }

    public function hasParticipant(int $entityId): bool
    {
        return in_array($entityId, $this->participants, true);
    }

    /**
     * @return int[]
     */
    public function getLocations(): array
    {
        return $this->locations;
    }

    /**
     * @param int[] $locations
     */
    public function setLocations(array $locations): void
    {
        $this->locations = array_values(array_unique($locations));
        $this->touch();
    }

    public function addLocation(int $entityId): void
    {
        if (!in_array($entityId, $this->locations, true)) {
            $this->locations[] = $entityId;
            $this->touch();
        }
    }

    public function hasLocation(int $entityId): bool
    {
        return in_array($entityId, $this->locations, true);
    }

    /**
     * Check if an entity is involved in this event (as participant or location)
     */
    public function involvesEntity(int $entityId): bool
    {
        return $this->hasParticipant($entityId)
            || $this->hasLocation($entityId)
            || ($this->eventEntityId !== null && $this->eventEntityId->value() === $entityId);
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id?->value(),
            'saga_id' => $this->sagaId->value(),
            'event_entity_id' => $this->eventEntityId?->value(),
            'canon_date' => $this->canonDate->value(),
            'normalized_timestamp' => $this->normalizedTimestamp->value(),
            'title' => $this->title,
            'description' => $this->description,
            'participants' => $this->participants,
            'locations' => $this->locations,
            'created_at' => $this->createdAt->format('c'),
            'updated_at' => $this->updatedAt->format('c'),
        ];
    }
}
