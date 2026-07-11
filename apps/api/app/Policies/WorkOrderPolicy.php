<?php

namespace App\Policies;

use App\Models\Evidence;
use App\Models\User;
use App\Models\WorkOrder;

class WorkOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, WorkOrder $workOrder): bool
    {
        return $user->isAdmin() || $workOrder->assigned_technician_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, WorkOrder $workOrder): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, WorkOrder $workOrder): bool
    {
        return $user->isAdmin();
    }

    public function transition(User $user, WorkOrder $workOrder): bool
    {
        return $this->view($user, $workOrder);
    }

    public function addEvidence(User $user, WorkOrder $workOrder): bool
    {
        return $this->view($user, $workOrder);
    }

    public function deleteEvidence(User $user, WorkOrder $workOrder, Evidence $evidence): bool
    {
        return $user->isAdmin()
            || ($this->view($user, $workOrder) && $evidence->uploaded_by === $user->id);
    }
}
