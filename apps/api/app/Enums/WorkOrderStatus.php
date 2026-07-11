<?php

namespace App\Enums;

enum WorkOrderStatus: string
{
    case Pending = 'pending';
    case Assigned = 'assigned';
    case EnRoute = 'en_route';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Assigned, self::Cancelled],
            self::Assigned => [self::Pending, self::EnRoute, self::Cancelled],
            self::EnRoute => [self::Assigned, self::InProgress, self::Cancelled],
            self::InProgress => [self::EnRoute, self::Completed, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }
}
