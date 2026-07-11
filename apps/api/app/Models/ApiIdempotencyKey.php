<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiIdempotencyKey extends Model
{
    protected $fillable = [
        'user_id', 'key', 'method', 'path', 'request_hash', 'response_status', 'response_body', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'response_status' => 'integer',
            'response_body' => 'array',
            'expires_at' => 'immutable_datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
