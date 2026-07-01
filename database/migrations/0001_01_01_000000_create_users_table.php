<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            // branch_id/role_id are NOT NULL in database.sql, but branches/roles
            // are seeded via application logic (not migrations) in this rebuild,
            // so they are left nullable here to keep `migrate:fresh` runnable
            // without seed data. This file keeps Laravel's fixed
            // 0001_01_01_000000 timestamp (so it still runs first, alongside
            // password_reset_tokens/sessions), which means it runs BEFORE the
            // 2026_07_01_* branches/sections/departments/roles migrations.
            // Left as plain nullable foreign id columns without ->constrained()
            // to avoid migration ordering failures, per task instructions.
            $table->foreignId('branch_id')->nullable();
            $table->foreignId('section_id')->nullable();
            $table->foreignId('department_id')->nullable();
            $table->foreignId('role_id')->nullable();
            $table->string('full_name', 150);
            $table->string('email')->unique();
            $table->string('username', 80)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role')->default('Staff');
            $table->string('phone', 20)->nullable();
            $table->string('profile_photo')->nullable();
            $table->boolean('is_active')->default(true);
            $table->dateTime('last_login')->nullable();
            $table->integer('failed_login_attempts')->default(0);
            $table->dateTime('last_login_attempt')->nullable();
            $table->string('password_reset_token')->nullable();
            $table->dateTime('token_expiry')->nullable();
            $table->string('remember_token', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
