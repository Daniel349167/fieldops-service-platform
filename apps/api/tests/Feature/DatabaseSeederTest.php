<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seed_is_complete_and_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseHas('users', ['email' => 'admin@fieldops.test', 'role' => 'admin']);
        $this->assertDatabaseHas('users', ['email' => 'tecnico@fieldops.test', 'role' => 'technician']);
        $this->assertDatabaseCount('work_orders', 3);
        $this->assertDatabaseCount('evidences', 1);
        $this->assertDatabaseCount('work_order_events', 4);
    }
}
