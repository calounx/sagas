<?php
declare(strict_types=1);

namespace SagaManager\Tests\Unit\Domain\Entity;

use PHPUnit\Framework\TestCase;
use SagaManager\Domain\Entity\CanonDate;
use SagaManager\Domain\Entity\EntityId;
use SagaManager\Domain\Entity\NormalizedTimestamp;
use SagaManager\Domain\Entity\SagaId;
use SagaManager\Domain\Entity\TimelineEvent;
use SagaManager\Domain\Entity\TimelineEventId;
use SagaManager\Domain\Exception\ValidationException;

class TimelineEventTest extends TestCase
{
    private SagaId $sagaId;
    private CanonDate $canonDate;
    private NormalizedTimestamp $timestamp;

    protected function setUp(): void
    {
        $this->sagaId = new SagaId(1);
        $this->canonDate = new CanonDate('19 BBY');
        $this->timestamp = new NormalizedTimestamp(-19);
    }

    public function test_can_create_basic_event(): void
    {
        $event = new TimelineEvent(
            sagaId: $this->sagaId,
            canonDate: $this->canonDate,
            normalizedTimestamp: $this->timestamp,
            title: 'Battle of Yavin'
        );

        $this->assertTrue($this->sagaId->equals($event->getSagaId()));
        $this->assertSame('19 BBY', $event->getCanonDate()->value());
        $this->assertSame(-19, $event->getNormalizedTimestamp()->value());
        $this->assertSame('Battle of Yavin', $event->getTitle());
        $this->assertNull($event->getId());
        $this->assertNull($event->getDescription());
        $this->assertSame([], $event->getParticipants());
        $this->assertSame([], $event->getLocations());
    }

    public function test_can_create_event_with_all_properties(): void
    {
        $event = new TimelineEvent(
            sagaId: $this->sagaId,
            canonDate: $this->canonDate,
            normalizedTimestamp: $this->timestamp,
            title: 'Battle of Yavin',
            description: 'Rebel Alliance destroys the Death Star',
            participants: [1, 2, 3],
            locations: [10, 20],
            eventEntityId: new EntityId(100),
            id: new TimelineEventId(42)
        );

        $this->assertSame(42, $event->getId()->value());
        $this->assertSame(100, $event->getEventEntityId()->value());
        $this->assertSame('Rebel Alliance destroys the Death Star', $event->getDescription());
        $this->assertSame([1, 2, 3], $event->getParticipants());
        $this->assertSame([10, 20], $event->getLocations());
    }

    public function test_throws_exception_for_empty_title(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Timeline event title cannot be empty');

        new TimelineEvent(
            sagaId: $this->sagaId,
            canonDate: $this->canonDate,
            normalizedTimestamp: $this->timestamp,
            title: ''
        );
    }

    public function test_set_id_updates_event_id(): void
    {
        $event = new TimelineEvent(
            sagaId: $this->sagaId,
            canonDate: $this->canonDate,
            normalizedTimestamp: $this->timestamp,
            title: 'Test Event'
        );

        $this->assertNull($event->getId());

        $event->setId(new TimelineEventId(99));

        $this->assertSame(99, $event->getId()->value());
    }

    public function test_update_title(): void
    {
        $event = new TimelineEvent(
            sagaId: $this->sagaId,
            canonDate: $this->canonDate,
            normalizedTimestamp: $this->timestamp,
            title: 'Original Title'
        );

        $event->updateTitle('Updated Title');

        $this->assertSame('Updated Title', $event->getTitle());
    }

    public function test_update_date(): void
    {
        $event = new TimelineEvent(
            sagaId: $this->sagaId,
            canonDate: $this->canonDate,
            normalizedTimestamp: $this->timestamp,
            title: 'Test Event'
        );

        $newCanonDate = new CanonDate('0 ABY');
        $newTimestamp = new NormalizedTimestamp(0);

        $event->updateDate($newCanonDate, $newTimestamp);

        $this->assertSame('0 ABY', $event->getCanonDate()->value());
        $this->assertSame(0, $event->getNormalizedTimestamp()->value());
    }

    public function test_add_participant(): void
    {
        $event = new TimelineEvent(
            sagaId: $this->sagaId,
            canonDate: $this->canonDate,
            normalizedTimestamp: $this->timestamp,
            title: 'Test Event'
        );

        $this->assertSame([], $event->getParticipants());

        $event->addParticipant(5);

        $this->assertSame([5], $event->getParticipants());
        $this->assertTrue($event->hasParticipant(5));
    }

    public function test_add_participant_prevents_duplicates(): void
    {
        $event = new TimelineEvent(
            sagaId: $this->sagaId,
            canonDate: $this->canonDate,
            normalizedTimestamp: $this->timestamp,
            title: 'Test Event',
            participants: [5]
        );

        $event->addParticipant(5);

        $this->assertSame([5], $event->getParticipants());
    }

    public function test_remove_participant(): void
    {
        $event = new TimelineEvent(
            sagaId: $this->sagaId,
            canonDate: $this->canonDate,
            normalizedTimestamp: $this->timestamp,
            title: 'Test Event',
            participants: [1, 2, 3]
        );

        $event->removeParticipant(2);

        $this->assertSame([1, 3], $event->getParticipants());
        $this->assertFalse($event->hasParticipant(2));
    }

    public function test_involves_entity(): void
    {
        $event = new TimelineEvent(
            sagaId: $this->sagaId,
            canonDate: $this->canonDate,
            normalizedTimestamp: $this->timestamp,
            title: 'Test Event',
            participants: [1, 2],
            locations: [10],
            eventEntityId: new EntityId(100)
        );

        $this->assertTrue($event->involvesEntity(1));   // Participant
        $this->assertTrue($event->involvesEntity(10));  // Location
        $this->assertTrue($event->involvesEntity(100)); // Event entity
        $this->assertFalse($event->involvesEntity(999)); // Not involved
    }

    public function test_to_array(): void
    {
        $event = new TimelineEvent(
            sagaId: $this->sagaId,
            canonDate: $this->canonDate,
            normalizedTimestamp: $this->timestamp,
            title: 'Test Event',
            participants: [1, 2],
            locations: [10],
            id: new TimelineEventId(42)
        );

        $array = $event->toArray();

        $this->assertSame(42, $array['id']);
        $this->assertSame(1, $array['saga_id']);
        $this->assertSame('19 BBY', $array['canon_date']);
        $this->assertSame(-19, $array['normalized_timestamp']);
        $this->assertSame('Test Event', $array['title']);
        $this->assertSame([1, 2], $array['participants']);
        $this->assertSame([10], $array['locations']);
    }
}
