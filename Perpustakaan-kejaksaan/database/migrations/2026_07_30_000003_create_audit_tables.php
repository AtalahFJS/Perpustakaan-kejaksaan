<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Log audit dan log pemakaian AI -- PRD 5.7 dan 6.3
 * (log audit disimpan minimal 1 tahun).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()
                ->constrained()->nullOnDelete();
            // mis. dokumen.lihat, dokumen.unduh, dokumen.hapus, pengguna.ubah
            $table->string('action', 60)->index();
            $table->string('subject_type', 60)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()
                ->constrained()->nullOnDelete();
            $table->foreignId('document_id')->nullable()
                ->constrained()->nullOnDelete();
            // ringkasan | chat | embedding
            $table->string('operation', 30)->index();
            $table->string('provider', 30)->default('anthropic');
            $table->string('model', 80);
            $table->unsignedInteger('tokens_in')->default(0);
            $table->unsignedInteger('tokens_out')->default(0);
            // F-7.3: estimasi biaya dalam USD
            $table->decimal('cost_estimate', 12, 6)->default(0);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->boolean('berhasil')->default(true);
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
        Schema::dropIfExists('audit_logs');
    }
};
