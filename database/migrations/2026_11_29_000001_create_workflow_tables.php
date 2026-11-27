<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** M3 Submission and approval workflow (CLAUDE.md §6, §7 M3). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 20)->unique(); // e.g. SUB-0142
            $table->foreignId('record_id')->nullable()->constrained('research_records')->nullOnDelete(); // set when published
            $table->foreignId('student_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('adviser_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('research_cluster_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('academic_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('program_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 500);
            $table->text('abstract');
            $table->enum('research_type', ['undergraduate_thesis', 'capstone_project', 'graduate_thesis', 'faculty_research']);
            $table->unsignedSmallInteger('year');
            // Metadata kept on the submission until it becomes a record (keywords 3–5, SDG numbers).
            $table->json('keywords');
            $table->json('sdgs');
            // The student ticks this when no IMRAD version exists; the URDC confirms at approval (§7 M2 slot 3).
            $table->boolean('imrad_not_applicable')->default(false);
            $table->enum('status', ['draft', 'submitted', 'endorsed', 'reviewed', 'published', 'returned', 'rejected'])->default('draft');
            $table->unsignedTinyInteger('current_step')->default(0);
            $table->decimal('max_similarity', 4, 3)->nullable();
            $table->enum('flag', ['original', 'similar', 'probable_duplicate'])->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'adviser_user_id']);
            $table->index(['status', 'research_cluster_id']);
        });

        Schema::create('submission_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('position');
        });

        // Every transition writes a row here; return and reject always carry a reason (§1 rule 5).
        Schema::create('submission_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('step');
            $table->foreignId('reviewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('decision', ['submit', 'resubmit', 'endorse', 'return', 'clear', 'revise', 'reject', 'approve']);
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['submission_id', 'created_at']);
        });

        Schema::table('record_files', function (Blueprint $table) {
            $table->foreign('submission_id')->references('id')->on('submissions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('record_files', function (Blueprint $table) {
            $table->dropForeign(['submission_id']);
        });
        Schema::dropIfExists('submission_reviews');
        Schema::dropIfExists('submission_members');
        Schema::dropIfExists('submissions');
    }
};
