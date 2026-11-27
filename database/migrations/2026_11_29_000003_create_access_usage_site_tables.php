<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M4 full-paper requests and search logs, M7 KPI snapshots, §8 landing page content,
 * §12 in-app notifications, §17 evaluator accounts (CLAUDE.md §6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_requests', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 20)->unique(); // e.g. AR-031
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('record_id')->constrained('research_records')->cascadeOnDelete();
            $table->text('purpose');
            $table->enum('status', ['pending', 'approved', 'declined'])->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        // Every search, including those with zero results (Table 7 visual 13).
        Schema::create('search_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('query', 200);
            $table->json('filters')->nullable();
            $table->unsignedInteger('results_count');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['results_count', 'created_at']);
        });

        // Filled nightly so dashboards load fast (§7 M7).
        Schema::create('kpi_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('scope_type', 20); // university | cluster | college | adviser
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('metric', 60);
            $table->string('period', 20); // e.g. 2025, 2025-11, all
            $table->decimal('value', 14, 2);
            $table->timestamp('computed_at');
            $table->unique(['scope_type', 'scope_id', 'metric', 'period']);
        });

        // [Not in manuscript] Landing page CMS: drafts and published versions (§8).
        Schema::create('site_contents', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('version');
            $table->json('data');
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->string('summary')->nullable(); // sections changed in this version
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'version']);
        });

        // In-app notifications (Laravel database channel), copies of the emails (§7 M3).
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        // Temporary evaluator accounts: evaluation copy only (§17).
        Schema::create('evaluator_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluator_accounts');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('site_contents');
        Schema::dropIfExists('kpi_snapshots');
        Schema::dropIfExists('search_logs');
        Schema::dropIfExists('access_requests');
    }
};
