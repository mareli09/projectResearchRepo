<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // [Not in manuscript] 6-digit codes for password recovery and for verifying a recovery contact (CLAUDE.md §7 M1).
        // Codes are stored only as hashes. They expire in 10 minutes, work once, and stop after 5 wrong tries.
        Schema::create('recovery_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('channel', ['tua_email', 'recovery_email', 'sms']);
            $table->enum('purpose', ['password_reset', 'verify_contact']);
            $table->string('code_hash');
            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'purpose', 'channel']);
        });

        // Every email is recorded here; failures show in System health (§12).
        Schema::create('mail_log', function (Blueprint $table) {
            $table->id();
            $table->string('to');
            $table->string('subject');
            $table->enum('status', ['sent', 'failed']);
            $table->unsignedTinyInteger('attempts')->default(1);
            $table->text('last_error')->nullable();
            $table->string('notification')->nullable();
            $table->timestamps();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_log');
        Schema::dropIfExists('recovery_codes');
    }
};
