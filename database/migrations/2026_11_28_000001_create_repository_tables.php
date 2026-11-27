<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** M2 Repository and metadata (CLAUDE.md §6, §7 M2). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_records', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 20)->unique(); // e.g. TUA-2024-0123
            $table->string('title', 500);
            $table->text('abstract');
            $table->unsignedSmallInteger('year');
            $table->enum('research_type', ['undergraduate_thesis', 'capstone_project', 'graduate_thesis', 'faculty_research']);
            $table->enum('degree_level', ['undergraduate', 'graduate', 'faculty']);
            $table->foreignId('academic_unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('adviser_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('adviser_name')->nullable();
            $table->enum('status', ['draft', 'published', 'withdrawn'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('downloads')->default(0);
            $table->enum('source', ['submission', 'google_form_migration', 'legacy_import'])->default('submission');
            // The URDC can mark the IMRAD version not applicable (§7 M2 slot 3).
            $table->boolean('imrad_not_applicable')->default(false);
            // All required file slots present. Kept in sync by RecordFiles::refreshCompleteness().
            $table->boolean('is_complete')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'year']);
            $table->index(['academic_unit_id', 'status']);
            $table->fullText(['title', 'abstract']);
        });

        Schema::create('record_authors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('record_id')->constrained('research_records')->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('position');
            $table->unique(['record_id', 'position']);
        });

        Schema::create('keywords', function (Blueprint $table) {
            $table->id();
            $table->string('term', 100)->unique();
        });

        Schema::create('record_keyword', function (Blueprint $table) {
            $table->foreignId('record_id')->constrained('research_records')->cascadeOnDelete();
            $table->foreignId('keyword_id')->constrained()->cascadeOnDelete();
            $table->enum('source', ['user', 'llm_accepted', 'llm_edited', 'import'])->default('user');
            $table->primary(['record_id', 'keyword_id']);
        });

        Schema::create('record_sdg', function (Blueprint $table) {
            $table->foreignId('record_id')->constrained('research_records')->cascadeOnDelete();
            $table->unsignedTinyInteger('sdg_id');
            $table->foreign('sdg_id')->references('id')->on('sdgs')->restrictOnDelete();
            $table->enum('source', ['user', 'llm_accepted', 'llm_edited', 'import'])->default('user');
            $table->primary(['record_id', 'sdg_id']);
        });

        Schema::create('record_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('record_id')->nullable()->constrained('research_records')->cascadeOnDelete();
            // Foreign key to submissions is added in Sprint 3 when that table exists.
            $table->unsignedBigInteger('submission_id')->nullable()->index();
            $table->enum('slot', ['abstract', 'full_paper', 'imrad', 'toa', 'references']);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 150);
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64)->index();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            // Older versions are kept; only the newest per slot is current.
            $table->boolean('is_current')->default(true);
            $table->timestamps();
            $table->index(['record_id', 'slot', 'is_current']);
        });

        // Every download is checked and logged (§1 rule 9).
        Schema::create('download_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('record_id')->nullable()->constrained('research_records')->nullOnDelete();
            $table->foreignId('record_file_id')->nullable()->constrained('record_files')->nullOnDelete();
            $table->string('slot', 20);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('download_logs');
        Schema::dropIfExists('record_files');
        Schema::dropIfExists('record_sdg');
        Schema::dropIfExists('record_keyword');
        Schema::dropIfExists('keywords');
        Schema::dropIfExists('record_authors');
        Schema::dropIfExists('research_records');
    }
};
