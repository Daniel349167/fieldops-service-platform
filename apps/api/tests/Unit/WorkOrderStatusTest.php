<?php

namespace Tests\Unit;

use App\Enums\WorkOrderStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class WorkOrderStatusTest extends TestCase
{
    #[DataProvider('transitionProvider')]
    public function test_status_machine_only_allows_declared_transitions(
        WorkOrderStatus $from,
        WorkOrderStatus $to,
        bool $expected,
    ): void {
        $this->assertSame($expected, $from->canTransitionTo($to));
    }

    public static function transitionProvider(): array
    {
        return [
            'pending can be assigned' => [WorkOrderStatus::Pending, WorkOrderStatus::Assigned, true],
            'pending cannot start directly' => [WorkOrderStatus::Pending, WorkOrderStatus::InProgress, false],
            'assigned can go en route' => [WorkOrderStatus::Assigned, WorkOrderStatus::EnRoute, true],
            'en route can start' => [WorkOrderStatus::EnRoute, WorkOrderStatus::InProgress, true],
            'in progress can complete' => [WorkOrderStatus::InProgress, WorkOrderStatus::Completed, true],
            'completed is terminal' => [WorkOrderStatus::Completed, WorkOrderStatus::Assigned, false],
            'cancelled is terminal' => [WorkOrderStatus::Cancelled, WorkOrderStatus::Pending, false],
        ];
    }
}
