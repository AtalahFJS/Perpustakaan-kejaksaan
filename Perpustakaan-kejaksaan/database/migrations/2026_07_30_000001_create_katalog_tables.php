<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel katalog inti: kategori, tag, dokumen.
 * Mengacu pada PRD 7.4 (Model Data) dan 5.2 (Modul Manajemen Dokumen).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            // Kategori bertingkat (mis. Peraturan > Peraturan Kejaksaan)
            $table->foreignId('parent_id')->nullable()
                ->constrained('categories')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();

            // F-2.3: metadata wajib
            $table->string('title');
            $table->string('author')->nullable();
            $table->string('publisher')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->foreignId('category_id')->nullable()
                ->constrained('categories')->nullOnDelete();

            // F-8.1: klasifikasi wajib -- publik | internal | terbatas
            $table->string('classification', 20)->default('internal');

            // F-2.4: metadata opsional
            $table->string('isbn', 32)->nullable();
            $table->string('document_number')->nullable();
            $table->unsignedInteger('page_count')->nullable();
            $table->string('language', 10)->default('id');
            $table->text('abstract')->nullable();

            // Berkas -- disimpan di disk privat, tidak pernah dilayani langsung
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('file_mime', 100)->default('application/pdf');
            $table->string('file_hash', 64)->nullable()->index();
            $table->string('cover_path')->nullable();

            // F-2.9: status pemrosesan
            $table->string('status', 20)->default('uploaded');
            $table->text('error_message')->nullable();
            $table->timestamp('ingested_at')->nullable();

            $table->foreignId('uploaded_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();
            // F-2.7: penghapusan bersifat soft delete
            $table->softDeletes();

            $table->index('status');
            $table->index('classification');
            $table->index(['classification', 'status']);
            $table->index('year');
        });

        Schema::create('document_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->unique(['document_id', 'tag_id']);
        });

        // F-3.1 / F-3.2: indeks pencarian teks penuh pada metadata dokumen.
        // Memakai konfigurasi 'simple' karena PostgreSQL tidak menyertakan
        // kamus Bahasa Indonesia secara bawaan; 'simple' tetap memberi
        // pencocokan kata yang benar tanpa stemming.
        DB::statement(<<<'SQL'
            ALTER TABLE documents
            ADD COLUMN search_vector tsvector
            GENERATED ALWAYS AS (
                setweight(to_tsvector('simple', coalesce(title, '')), 'A') ||
                setweight(to_tsvector('simple', coalesce(author, '')), 'B') ||
                setweight(to_tsvector('simple', coalesce(document_number, '')), 'B') ||
                setweight(to_tsvector('simple', coalesce(abstract, '')), 'C')
            ) STORED
        SQL);

        DB::statement('CREATE INDEX documents_search_vector_idx ON documents USING GIN (search_vector)');

        // Pencarian judul yang toleran salah ketik (trigram).
        DB::statement('CREATE INDEX documents_title_trgm_idx ON documents USING GIN (title gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('document_tag');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('categories');
    }
};
