<?php
declare(strict_types=1);

namespace SagaManager\Domain\Entity;

/**
 * Entity Type Enumeration
 *
 * Defines the types of entities that can exist in a saga
 */
enum EntityType: string
{
    case CHARACTER = 'character';
    case LOCATION = 'location';
    case EVENT = 'event';
    case FACTION = 'faction';
    case ARTIFACT = 'artifact';
    case CONCEPT = 'concept';

    public function label(): string
    {
        return match($this) {
            self::CHARACTER => 'Character',
            self::LOCATION => 'Location',
            self::EVENT => 'Event',
            self::FACTION => 'Faction',
            self::ARTIFACT => 'Artifact',
            self::CONCEPT => 'Concept',
        };
    }
}
