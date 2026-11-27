<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Evaluation data (CLAUDE.md §7 M5 "AI performance", §7 M7 "Dashboard task mode", §17):
 * - the labeled screening test set: study–record pairs, each labeled duplicate or distinct by
 *   coordinators; the majority of 3 is the reference label;
 * - dashboard task runs: time and completion of the 5 set tasks per evaluator.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_pairs', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 20)->unique(); // TP-001
            // The "new study" side: a stored record's text, or a prepared text from the CSV import.
            $table->foreignId('left_record_id')->nullable()->constrained('research_records')->nullOnDelete();
            $table->string('left_title', 500);
            $table->text('left_abstract');
            $table->json('left_keywords');
            // The stored record it is compared with.
            $table->foreignId('record_id')->constrained('research_records')->cascadeOnDelete();
            $table->decimal('score', 4, 3)->nullable();
            $table->json('shared_terms')->nullable();
            $table->string('vectorizer_version', 40)->nullable();
            $table->enum('source', ['sample_neighbour', 'sample_random', 'import']);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('test_pair_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_pair_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('label', ['duplicate', 'distinct']);
            $table->timestamps();
            $table->unique(['test_pair_id', 'user_id']);
        });

        Schema::create('dashboard_task_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('task_no'); // 1..5
            // DATETIME, not TIMESTAMP: MariaDB gives the first NOT NULL TIMESTAMP column
            // ON UPDATE CURRENT_TIMESTAMP, which would reset the start time when the task is finished.
            $table->dateTime('started_at');
            $table->dateTime('finished_at')->nullable();
            $table->enum('status', ['started', 'done', 'skipped'])->default('started');
            $table->string('answer', 500)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'task_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_task_runs');
        Schema::dropIfExists('test_pair_labels');
        Schema::dropIfExists('test_pairs');
    }
};
