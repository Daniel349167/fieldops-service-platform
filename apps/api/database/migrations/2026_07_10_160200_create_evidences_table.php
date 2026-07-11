<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidences', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('file_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->string('storage_path', 500);
            $table->string('checksum', 128)->nullable();
            $table->timestampTz('captured_at')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('version')->default(1);
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->index(['updated_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidences');
    }
};
