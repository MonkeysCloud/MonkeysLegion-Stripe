<?php

namespace MonkeysLegion\Stripe\Tests\Unit\Storage;

use MonkeysLegion\Stripe\Tests\TestCase;
use MonkeysLegion\Stripe\Storage\Stores\InMemoryStore;

class InMemoryStoreTest extends TestCase
{
    private InMemoryStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = new InMemoryStore();
    }

    public function testBasicFunctionality(): void
    {
        $eventId = 'evt_' . uniqid();

        // Verify event is not processed yet
        $this->assertFalse($this->store->isProcessed($eventId));

        // Mark as processed
        $this->store->markAsProcessed($eventId);

        // Verify event is now processed
        $this->assertTrue($this->store->isProcessed($eventId));

        // Remove event
        $this->store->removeEvent($eventId);

        // Verify event is no longer processed
        $this->assertFalse($this->store->isProcessed($eventId));
    }

    public function testWithEventData(): void
    {
        $eventId = 'evt_' . uniqid();
        $eventData = ['charge_id' => 'ch_123', 'amount' => 2000];

        // Mark with data
        $this->store->markAsProcessed($eventId, null, $eventData);

        // Get all events
        $events = $this->store->getAllEvents();

        // Verify data was stored
        $this->assertCount(1, $events);
        $this->assertEquals($eventData, $events[0]);
    }

    public function testExpiration(): void
    {
        $eventId = 'evt_' . uniqid();

        // Mark with short TTL
        $this->store->markAsProcessed($eventId, 1);
        $this->assertTrue($this->store->isProcessed($eventId));

        // Wait for expiration
        sleep(2);

        // Should now be expired
        $this->assertFalse($this->store->isProcessed($eventId));
    }

    public function testCleanupExpired(): void
    {
        $eventId1 = 'evt_' . uniqid();
        $eventId2 = 'evt_' . uniqid();

        // Mark one with TTL, one without
        $this->store->markAsProcessed($eventId1, 1);
        $this->store->markAsProcessed($eventId2);

        // Wait for expiration
        sleep(2);

        // Manually cleanup
        $this->store->cleanupExpired();

        // Only the TTL one should be gone
        $this->assertFalse($this->store->isProcessed($eventId1));
        $this->assertTrue($this->store->isProcessed($eventId2));
    }

    public function testClearAll(): void
    {
        $eventId1 = 'evt_' . uniqid();
        $eventId2 = 'evt_' . uniqid();

        // Mark multiple events
        $this->store->markAsProcessed($eventId1);
        $this->store->markAsProcessed($eventId2);

        // Clear all
        $this->store->clearAll();

        // None should be processed
        $this->assertFalse($this->store->isProcessed($eventId1));
        $this->assertFalse($this->store->isProcessed($eventId2));

        // Get all should return empty
        $this->assertEmpty($this->store->getAllEvents());
    }

    public function testEdgeCaseZeroTTL(): void
    {
        $eventId = 'evt_' . uniqid();

        // Mark with zero TTL
        $this->store->markAsProcessed($eventId, 0);

        // Should expire immediately
        $this->assertFalse($this->store->isProcessed($eventId));
    }

    public function testEdgeCaseNegativeTTL(): void
    {
        $eventId = 'evt_' . uniqid();

        // Mark with negative TTL
        $this->store->markAsProcessed($eventId, -10);

        // Should expire immediately
        $this->assertFalse($this->store->isProcessed($eventId));
    }
}
