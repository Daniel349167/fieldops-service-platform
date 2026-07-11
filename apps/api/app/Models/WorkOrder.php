<?php

namespace App\Models;

use App\Enums\WorkOrderPriority;
use App\Enums\WorkOrderStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkOrder extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'title', 'description', 'customer_name', 'customer_phone', 'customer_email',
        'address_line', 'district', 'city', 'latitude', 'longitude', 'priority', 'status',
        'assigned_technician_id', 'scheduled_at', 'version',
    ];

    protected function casts(): array
    {
        return [
            'priority' => WorkOrderPriority::class,
            'status' => WorkOrderStatus::class,
            'scheduled_at' => 'immutable_datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'version' => 'integer',
        ];
    }

    public function assignedTechnician()
    {
        return $this->belongsTo(User::class, 'assigned_technician_id');
    }

    public function evidences()
    {
        return $this->hasMany(Evidence::class);
    }

    public function events()
    {
        return $this->hasMany(WorkOrderEvent::class)->latest();
    }
}
