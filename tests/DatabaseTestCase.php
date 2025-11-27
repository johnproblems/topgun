<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class DatabaseTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Only run database setup if we have a working database connection
        if ($this->canConnectToDatabase()) {
            // Ensure we're using the testing database
            config(['database.default' => 'testing']);

            // Run migrations for testing
            $this->artisan('migrate:fresh');

            // Seed the database with test data
            $this->seed();
        } else {
            $this->markTestSkipped('Database connection not available for testing');
        }
    }

    protected function canConnectToDatabase(): bool
    {
        try {
            \DB::connection('testing')->getPdo();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
