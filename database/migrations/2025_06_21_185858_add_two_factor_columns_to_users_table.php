<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Only add columns if they don't exist
            if (!Schema::hasColumn('users', 'username')) {
                $table->string('username')->unique()->after('name');
            }
            if (!Schema::hasColumn('users', 'two_factor_enabled')) {
                $table->boolean('two_factor_enabled')->default(false)->after('remember_token');
            }
            if (!Schema::hasColumn('users', 'two_factor_secret')) {
                $table->text('two_factor_secret')->nullable()->after('two_factor_enabled');
            }
            if (!Schema::hasColumn('users', 'two_factor_recovery_codes')) {
                $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            }
            if (!Schema::hasColumn('users', 'two_factor_confirmed_at')) {
                $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            }
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable()->after('two_factor_confirmed_at');
            }
            if (!Schema::hasColumn('users', 'phone_verified')) {
                $table->boolean('phone_verified')->default(false)->after('phone');
            }
            if (!Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('phone_verified');
            }
            if (!Schema::hasColumn('users', 'last_login_ip')) {
                $table->string('last_login_ip')->nullable()->after('last_login_at');
            }
            if (!Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('last_login_ip');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Only drop columns if they exist
            $columnsToDrop = [];
            
            if (Schema::hasColumn('users', 'username')) {
                $columnsToDrop[] = 'username';
            }
            if (Schema::hasColumn('users', 'two_factor_enabled')) {
                $columnsToDrop[] = 'two_factor_enabled';
            }
            if (Schema::hasColumn('users', 'two_factor_secret')) {
                $columnsToDrop[] = 'two_factor_secret';
            }
            if (Schema::hasColumn('users', 'two_factor_recovery_codes')) {
                $columnsToDrop[] = 'two_factor_recovery_codes';
            }
            if (Schema::hasColumn('users', 'two_factor_confirmed_at')) {
                $columnsToDrop[] = 'two_factor_confirmed_at';
            }
            if (Schema::hasColumn('users', 'phone')) {
                $columnsToDrop[] = 'phone';
            }
            if (Schema::hasColumn('users', 'phone_verified')) {
                $columnsToDrop[] = 'phone_verified';
            }
            if (Schema::hasColumn('users', 'last_login_at')) {
                $columnsToDrop[] = 'last_login_at';
            }
            if (Schema::hasColumn('users', 'last_login_ip')) {
                $columnsToDrop[] = 'last_login_ip';
            }
            if (Schema::hasColumn('users', 'is_active')) {
                $columnsToDrop[] = 'is_active';
            }
            
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};