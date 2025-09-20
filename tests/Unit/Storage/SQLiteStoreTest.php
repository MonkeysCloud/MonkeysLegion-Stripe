<?php

namespace MonkeysLegion\Stripe\Tests\Unit\Storage;

use MonkeysLegion\Stripe\Tests\TestCase;
use MonkeysLegion\Stripe\Storage\Stores\SQLiteStore;

class SQLiteStoreTest extends TestCase
{
    private SQLiteStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        // Use in-memory SQLite for testing
        $this->store = new SQLiteStore();
    }

    public function testMarkAsProcessedAndIsProcessed(): void
    {
        $eventId = 'evt_' . uniqid();

        // Verify event is not processed yet
        $this->assertFalse($this->store->isProcessed($eventId));

        // Mark as processed
        $this->store->markAsProcessed($eventId);

        // Verify event is now processed
        $this->assertTrue($this->store->isProcessed($eventId));
    }

    public function testMarkAsProcessedWithTTL(): void
    {
        $eventId = 'evt_' . uniqid();

        // Mark as processed with very short TTL
        $this->store->markAsProcessed($eventId, 1, ['test' => 'data']);

        // Verify event is processed
        $this->assertTrue($this->store->isProcessed($eventId));

        // Wait for TTL to expire
        sleep(2);

        // Verify event is no longer considered processed
        $this->assertFalse($this->store->isProcessed($eventId));
    }

    public function testRemoveEvent(): void
    {
        $eventId = 'evt_' . uniqid();

        // Mark as processed
        $this->store->markAsProcessed($eventId);
        $this->assertTrue($this->store->isProcessed($eventId));

        // Remove event
        $this->store->removeEvent($eventId);

        // Verify event is no longer processed
        $this->assertFalse($this->store->isProcessed($eventId));
    }

    public function testClearAll(): void
    {
        $eventId1 = 'evt_' . uniqid();
        $eventId2 = 'evt_' . uniqid();

        // Mark multiple events as processed
        $this->store->markAsProcessed($eventId1);
        $this->store->markAsProcessed($eventId2);

        // Verify events are processed
        $this->assertTrue($this->store->isProcessed($eventId1));
        $this->assertTrue($this->store->isProcessed($eventId2));

        // Clear all events
        $this->store->clearAll();

        // Verify no events are processed
        $this->assertFalse($this->store->isProcessed($eventId1));
        $this->assertFalse($this->store->isProcessed($eventId2));
    }

    public function testCleanupExpired(): void
    {
        $eventId1 = 'evt_' . uniqid();
        $eventId2 = 'evt_' . uniqid();

        // Mark one event with TTL, one without
        $this->store->markAsProcessed($eventId1, 1); // 1 second TTL
        $this->store->markAsProcessed($eventId2); // No TTL

        // Wait for TTL to expire
        sleep(2);

        // Cleanup expired events
        $this->store->cleanupExpired();

        // Verify only the TTL event was cleaned up
        $this->assertFalse($this->store->isProcessed($eventId1));
        $this->assertTrue($this->store->isProcessed($eventId2));
    }

    public function testGetAllEvents(): void
    {
        $eventId1 = 'evt_' . uniqid();
        $eventId2 = 'evt_' . uniqid();
        $data1 = ['test' => 'data1'];
        $data2 = ['test' => 'data2'];

        // Clear any existing events
        $this->store->clearAll();

        // Mark events with data
        $this->store->markAsProcessed($eventId1, null, $data1);
        $this->store->markAsProcessed($eventId2, null, $data2);

        // Get all events
        $events = $this->store->getAllEvents();

        // Verify events contain the correct data
        $this->assertCount(2, $events);
        $this->assertContains($data1, $events);
        $this->assertContains($data2, $events);
    }

    public function testEdgeCaseInvalidJsonData(): void
    {
        $eventId = 'evt_' . uniqid();
        $invalidData = ['test' => fopen('php://memory', 'r')]; // Cannot be JSON encoded

        // This should throw due to JSON encoding failure
        $this->expectException(\JsonException::class);
        $this->store->markAsProcessed($eventId, null, $invalidData);
    }
}
