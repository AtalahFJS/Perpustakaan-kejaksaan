<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel yang dipakai pipeline AI: potongan teks + vektor, ringkasan,
 * dan percakapan. Mengacu pada PRD 7.4, 5.5, dan 5.6.
 *
 * Dimensi vektor 768 mengikuti model embedding multibahasa
 * intfloat/multilingual-e5-base yang dijalankan lokal oleh layanan AI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->text('content');
            // Nomor halaman asal -- dasar sitasi (F-6.3)
            $table->unsignedInteger('page_start');
            $table->unsignedInteger('page_end');
            $table->unsignedInteger('token_count')->default(0);
            $table->timestamps();

            $table->unique(['document_id', 'chunk_index']);
            $table->index('document_id');
        });

        // Kolom vektor tidak tersedia di Blueprint Laravel -- pakai SQL mentah.
        DB::statement('ALTER TABLE chunks ADD COLUMN embedding vector(768)');

        // Indeks HNSW untuk pencarian kemiripan kosinus (PRD 7.4).
        DB::statement(<<<'SQL'
            CREATE INDEX chunks_embedding_hnsw_idx ON chunks
            USING hnsw (embedding vector_cosine_ops)
            WITH (m = 16, ef_construction = 64)
        SQL);

        // F-3.2: pencarian teks penuh pada isi potongan, dipakai juga sebagai
        // separuh kata kunci dari pencarian hibrida (PRD 7.6 langkah 4).
        DB::statement(<<<'SQL'
            ALTER TABLE chunks
            ADD COLUMN search_vector tsvector
            GENERATED ALWAYS AS (to_tsvector('simple', coalesce(content, ''))) STORED
        SQL);

        DB::statement('CREATE INDEX chunks_search_vector_idx ON chunks USING GIN (search_vector)');

        Schema::create('summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            // F-5.2: singkat | poin_kunci | per_bagian
            $table->string('type', 30);
            // Label bagian, mis. "Halaman 1-40" atau judul bab
            $table->string('scope_label')->nullable();
            $table->unsignedInteger('urutan')->default(0);
            $table->text('content');
            $table->string('model_used')->nullable();
            // F-5.5: pustakawan meninjau sebelum ringkasan tampil
            $table->boolean('is_approved')->default(true);
            $table->foreignId('approved_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['document_id', 'type']);
        });

        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'document_id']);
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_session_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20); // user | assistant
            $table->text('content');
            // F-6.3: daftar sitasi {chunk_id, halaman, kutipan}
            $table->jsonb('citations')->nullable();
            $table->unsignedInteger('tokens_in')->default(0);
            $table->unsignedInteger('tokens_out')->default(0);
            $table->unsignedInteger('latency_ms')->default(0);
            // F-6.7: umpan balik suka / tidak suka
            $table->smallInteger('feedback')->nullable();
            $table->timestamps();

            $table->index('chat_session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_sessions');
        Schema::dropIfExists('summaries');
        Schema::dropIfExists('chunks');
    }
};
