<?php

namespace Database\Factories;

use App\Models\Evidence;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Evidence> */
class EvidenceFactory extends Factory
{
    protected $model = Evidence::class;

    public function definition(): array
    {
        $name = Str::lower(Str::random(12)).'.jpg';

        return [
            'work_order_id' => WorkOrder::factory(),
            'uploaded_by' => User::factory()->technician(),
            'file_name' => $name,
            'mime_type' => 'image/jpeg',
            'size_bytes' => fake()->numberBetween(50_000, 2_000_000),
            'storage_path' => 'evidences/'.fake()->uuid().'/'.$name,
            'checksum' => hash('sha256', fake()->uuid()),
            'captured_at' => now(),
            'metadata' => ['source' => 'mobile', 'offline' => false],
            'version' => 1,
        ];
    }
}
