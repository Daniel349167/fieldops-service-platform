<?php

namespace Database\Factories;

use App\Enums\WorkOrderPriority;
use App\Enums\WorkOrderStatus;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WorkOrder> */
class WorkOrderFactory extends Factory
{
    protected $model = WorkOrder::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(5),
            'description' => fake()->paragraph(),
            'customer_name' => fake()->name(),
            'customer_phone' => fake()->phoneNumber(),
            'customer_email' => fake()->safeEmail(),
            'address_line' => fake()->streetAddress(),
            'district' => fake()->randomElement(['Miraflores', 'San Isidro', 'Surco', 'Ate']),
            'city' => 'Lima',
            'latitude' => fake()->latitude(-12.25, -11.85),
            'longitude' => fake()->longitude(-77.20, -76.85),
            'priority' => fake()->randomElement(WorkOrderPriority::cases()),
            'status' => WorkOrderStatus::Pending,
            'assigned_technician_id' => null,
            'scheduled_at' => fake()->dateTimeBetween('now', '+2 weeks'),
            'version' => 1,
        ];
    }

    public function assigned(?User $technician = null): static
    {
        return $this->state(fn (): array => [
            'assigned_technician_id' => $technician?->id ?? User::factory()->technician(),
            'status' => WorkOrderStatus::Assigned,
        ]);
    }

    public function inProgress(?User $technician = null): static
    {
        return $this->state(fn (): array => [
            'assigned_technician_id' => $technician?->id ?? User::factory()->technician(),
            'status' => WorkOrderStatus::InProgress,
        ]);
    }
}
