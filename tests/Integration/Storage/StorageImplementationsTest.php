<?php

namespace MonkeysLegion\Stripe\Tests\Integration\Storage;

use MonkeysLegion\Stripe\Tests\TestCase;
use MonkeysLegion\Stripe\Storage\Stores\SQLiteStore;
use MonkeysLegion\Stripe\Storage\Stores\InMemoryStore;

class StorageImplementationsTest extends TestCase
{
    private SQLiteStore $sqliteStore;
    private InMemoryStore $memoryStore;
    private string $tempDbPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Create temporary SQLite database
        $this->tempDbPath = sys_get_temp_dir() . '/stripe_test_' . uniqid() . '.sqlite';
        $this->sqliteStore = new SQLiteStore($this->tempDbPath);
        $this->memoryStore = new InMemoryStore();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        try {
            if (file_exists($this->tempDbPath)) {
                @unlink($this->tempDbPath);
            }
        } catch (\Exception $e) {
            // Ignore errors during cleanup
        }
    }

    /**
     * Test that different storage implementations behave consistently
     */
    public function testStorageConsistency(): void
    {
        $eventId = 'evt_' . uniqid();
        $eventData = ['test' => 'data', 'amount' => 1000];

        // Test both implementations with the same operations
        foreach ([$this->sqliteStore, $this->memoryStore] as $store) {
            // Initially not processed
            $this->assertFalse($store->isProcessed($eventId));

            // Mark as processed
            $store->markAsProcessed($eventId, 3600, $eventData);

            // Should now be processed
            $this->assertTrue($store->isProcessed($eventId));

            // Events should contain our data
            $events = $store->getAllEvents();
            $this->assertCount(1, $events);
            $this->assertEquals($eventData, $events[0]);

            // Remove event
            $store->removeEvent($eventId);

            // Should no longer be processed
            $this->assertFalse($store->isProcessed($eventId));

            // Events should be empty
            $this->assertEmpty($store->getAllEvents());
        }
    }

    /**
     * Test persistence with SQLite implementation
     */
    public function testSQLitePersistence(): void
    {
        $eventId = 'evt_' . uniqid();

        // Store an event
        $this->sqliteStore->markAsProcessed($eventId);

        // Create a new instance of SQLiteStore pointing to the same file
        $newStore = new SQLiteStore($this->tempDbPath);

        // Should still find the event
        $this->assertTrue($newStore->isProcessed($eventId));
    }

    /**
     * Test cleanup of expired events
     */
    public function testExpiredEventsCleanup(): void
    {
        $eventId1 = 'evt_' . uniqid();
        $eventId2 = 'evt_' . uniqid();

        // Test both implementations
        foreach ([$this->sqliteStore, $this->memoryStore] as $store) {
            // Mark one with short TTL, one with no TTL
            $store->markAsProcessed($eventId1, 1); // 1 second TTL
            $store->markAsProcessed($eventId2); // No TTL

            // Wait for expiration
            sleep(2);

            // Run cleanup
            $store->cleanupExpired();

            // Check results
            $this->assertFalse($store->isProcessed($eventId1), 'Event with TTL should be expired');
            $this->assertTrue($store->isProcessed($eventId2), 'Event without TTL should remain');

            // Clear for next test
            $store->clearAll();
        }
    }

    /**
     * Test high volume of events
     */
    public function testHighVolume(): void
    {
        // Only test SQLite as it's the most likely to have performance issues
        $events = [];

        // Create 100 events
        for ($i = 0; $i < 100; $i++) {
            $eventId = 'evt_' . uniqid();
            $events[] = $eventId;
            $this->sqliteStore->markAsProcessed($eventId, null, ['index' => $i]);
        }

        // Verify all are processed
        foreach ($events as $eventId) {
            $this->assertTrue($this->sqliteStore->isProcessed($eventId));
        }

        // Get all events
        $allEvents = $this->sqliteStore->getAllEvents();
        $this->assertCount(100, $allEvents);

        // Clear all
        $this->sqliteStore->clearAll();

        // Verify none are processed
        foreach ($events as $eventId) {
            $this->assertFalse($this->sqliteStore->isProcessed($eventId));
        }
    }
}
