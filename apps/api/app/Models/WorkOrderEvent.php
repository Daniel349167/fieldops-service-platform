<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class WorkOrderEvent extends Model
{
    use HasUlids;

    protected $fillable = ['work_order_id', 'actor_id', 'type', 'from_status', 'to_status', 'note', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class)->withTrashed();
    }
}
