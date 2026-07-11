<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Evidence extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'evidences';

    protected $fillable = [
        'work_order_id', 'uploaded_by', 'file_name', 'mime_type', 'size_bytes',
        'storage_path', 'checksum', 'captured_at', 'metadata', 'version',
    ];

    protected function casts(): array
    {
        return ['captured_at' => 'immutable_datetime', 'metadata' => 'array', 'version' => 'integer'];
    }

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
