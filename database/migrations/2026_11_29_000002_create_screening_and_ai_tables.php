<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** M5 Similarity screening, M6 Thematic clustering, §10 LLM suggestions (CLAUDE.md §6). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('similarity_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->timestamp('run_at');
            $table->string('vectorizer_version', 40)->nullable();
            $table->decimal('top_score', 4, 3);
            $table->enum('flag', ['original', 'similar', 'probable_duplicate']);
            $table->timestamps();
        });

        Schema::create('similarity_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('check_id')->constrained('similarity_checks')->cascadeOnDelete();
            $table->foreignId('record_id')->constrained('research_records')->cascadeOnDelete();
            $table->decimal('score', 4, 3);
            $table->unsignedTinyInteger('rank');
            $table->json('shared_terms');
            $table->unique(['check_id', 'rank']);
        });

        // The coordinator always decides; each decision feeds the calibration set (§7 M5).
        Schema::create('screening_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coordinator_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('matched_record_id')->nullable()->constrained('research_records')->nullOnDelete();
            $table->enum('decision', ['clear', 'revise', 'reject']);
            $table->text('reason')->nullable();
            $table->enum('label_for_calibration', ['duplicate', 'distinct'])->nullable();
            $table->timestamps();
        });

        // Every LLM suggestion and the user's action on it (§10). Never changes a record by itself.
        Schema::create('llm_suggestions', function (Blueprint $table) {
            $table->id();
            $table->enum('kind', ['keyword', 'sdg', 'research_type', 'theme_label']);
            $table->morphs('subject'); // submission or cluster
            $table->string('suggested');
            $table->string('final')->nullable();
            $table->enum('action', ['pending', 'accepted', 'edited', 'rejected'])->default('pending');
            $table->unsignedTinyInteger('rating')->nullable(); // 1–4, theme labels
            $table->string('provider', 40);
            $table->unsignedInteger('latency_ms')->nullable();
            $table->boolean('fallback_used')->default(false);
            $table->foreignId('acted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();
            $table->index(['kind', 'action']);
        });

        Schema::create('cluster_runs', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 20)->unique(); // e.g. RUN-06
            $table->unsignedTinyInteger('k');
            $table->decimal('silhouette', 6, 4)->nullable();
            $table->decimal('davies_bouldin', 6, 3)->nullable();
            $table->unsignedInteger('n_records');
            $table->json('params');
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('trigger_label')->nullable(); // "Scheduler (1st sem)"
            $table->enum('state', ['queued', 'running', 'done', 'failed', 'current', 'archived'])->default('queued');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('clusters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cluster_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('number');
            $table->json('top_terms');
            $table->string('label')->nullable();
            $table->enum('label_status', ['pending', 'confirmed'])->default('pending');
            $table->unsignedInteger('size')->default(0);
            $table->decimal('x', 8, 3)->default(0); // centroid on the 2-D projection
            $table->decimal('y', 8, 3)->default(0);
            $table->timestamps();
            $table->unique(['cluster_run_id', 'number']);
        });

        Schema::create('record_cluster', function (Blueprint $table) {
            $table->foreignId('record_id')->constrained('research_records')->cascadeOnDelete();
            $table->foreignId('cluster_id')->constrained()->cascadeOnDelete();
            $table->decimal('x', 8, 3); // 2-D projection for the theme map
            $table->decimal('y', 8, 3);
            $table->primary(['record_id', 'cluster_id']);
            $table->index('cluster_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_cluster');
        Schema::dropIfExists('clusters');
        Schema::dropIfExists('cluster_runs');
        Schema::dropIfExists('llm_suggestions');
        Schema::dropIfExists('screening_decisions');
        Schema::dropIfExists('similarity_matches');
        Schema::dropIfExists('similarity_checks');
    }
};
