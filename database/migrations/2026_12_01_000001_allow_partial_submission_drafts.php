<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Save draft" on Submit a study (CLAUDE.md §7 M3 state "draft"): a draft can be saved before
 * every field is filled in, so these columns may be empty while the status is draft.
 * Submitting still requires all of them (SubmissionRules).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->text('abstract')->nullable()->change();
            $table->enum('research_type', ['undergraduate_thesis', 'capstone_project', 'graduate_thesis', 'faculty_research'])->nullable()->change();
            $table->unsignedSmallInteger('year')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->text('abstract')->nullable(false)->change();
            $table->enum('research_type', ['undergraduate_thesis', 'capstone_project', 'graduate_thesis', 'faculty_research'])->nullable(false)->change();
            $table->unsignedSmallInteger('year')->nullable(false)->change();
        });
    }
};
