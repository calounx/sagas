<?php
declare(strict_types=1);

namespace SagaManagerCore\Domain\Entity;

/**
 * Enum representing the types of entities in a saga
 */
enum EntityType: string
{
    case CHARACTER = 'character';
    case LOCATION = 'location';
    case EVENT = 'event';
    case FACTION = 'faction';
    case ARTIFACT = 'artifact';
    case CONCEPT = 'concept';

    /**
     * Get human-readable label for the entity type
     */
    public function label(): string
    {
        return match ($this) {
            self::CHARACTER => 'Character',
            self::LOCATION => 'Location',
            self::EVENT => 'Event',
            self::FACTION => 'Faction',
            self::ARTIFACT => 'Artifact',
            self::CONCEPT => 'Concept',
        };
    }

    /**
     * Get description of the entity type
     */
    public function description(): string
    {
        return match ($this) {
            self::CHARACTER => 'A person, creature, or sentient being in the saga',
            self::LOCATION => 'A place, planet, realm, or geographical location',
            self::EVENT => 'A significant occurrence or happening in the timeline',
            self::FACTION => 'An organization, group, house, or political entity',
            self::ARTIFACT => 'An important object, weapon, or item of significance',
            self::CONCEPT => 'An abstract idea, philosophy, or system (e.g., The Force)',
        };
    }

    /**
     * Get icon identifier for the entity type
     */
    public function icon(): string
    {
        return match ($this) {
            self::CHARACTER => 'dashicons-admin-users',
            self::LOCATION => 'dashicons-location-alt',
            self::EVENT => 'dashicons-calendar-alt',
            self::FACTION => 'dashicons-groups',
            self::ARTIFACT => 'dashicons-star-filled',
            self::CONCEPT => 'dashicons-lightbulb',
        };
    }
}
