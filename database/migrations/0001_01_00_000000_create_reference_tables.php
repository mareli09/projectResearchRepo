<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reference data (CLAUDE.md §5, §6). Runs before the users table because
 * users point to an academic unit and a program.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_clusters', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('number')->unique();
            $table->string('name');
            // Coordinator FK is added after users exists (see the users migration).
            $table->unsignedBigInteger('coordinator_user_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('academic_units', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name');
            // Cluster membership is an assumption until the URDC confirms it, so it stays editable data.
            $table->foreignId('research_cluster_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_unit_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('degree_level', ['undergraduate', 'graduate', 'faculty']);
            $table->timestamps();
            $table->unique(['academic_unit_id', 'name']);
        });

        Schema::create('sdgs', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('name');
        });

        // Thresholds, schedule, file limits, LLM, session, recovery, site (§6).
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('sdgs');
        Schema::dropIfExists('programs');
        Schema::dropIfExists('academic_units');
        Schema::dropIfExists('research_clusters');
    }
};
