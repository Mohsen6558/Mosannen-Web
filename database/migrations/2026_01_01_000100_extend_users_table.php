<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Staff sign in with a username; the legacy app had no e-mail at all.
            $table->string('username', 60)->unique()->after('id');
            $table->string('full_name')->nullable()->after('name');
            $table->boolean('is_active')->default(true)->after('password');

            // Legacy passwords were stored in plaintext. They are never imported;
            // every migrated account starts with a random hash and this flag set.
            $table->boolean('must_change_password')->default(false)->after('is_active');

            $table->unsignedInteger('legacy_id')->nullable()->unique()->after('remember_token');
            $table->timestamp('last_login_at')->nullable();
            $table->softDeletes();
        });

        // E-mail is optional for clinic staff, so it cannot stay a required unique.
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'username', 'full_name', 'is_active', 'must_change_password',
                'legacy_id', 'last_login_at', 'deleted_at',
            ]);
        });
    }
};
