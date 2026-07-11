<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_orders', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('customer_name');
            $table->string('customer_phone', 40)->nullable();
            $table->string('customer_email')->nullable();
            $table->string('address_line');
            $table->string('district')->nullable();
            $table->string('city')->default('Lima');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('priority', 16)->default('normal')->index();
            $table->string('status', 24)->default('pending')->index();
            $table->foreignId('assigned_technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('scheduled_at')->nullable()->index();
            $table->unsignedBigInteger('version')->default(1);
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->index(['assigned_technician_id', 'status']);
            $table->index(['updated_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_orders');
    }
};
