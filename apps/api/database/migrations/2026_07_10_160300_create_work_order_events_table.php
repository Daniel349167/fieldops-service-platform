<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 32);
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24)->nullable();
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestampsTz();
            $table->index(['work_order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_events');
    }
};
