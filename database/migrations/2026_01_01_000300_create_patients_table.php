<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();

            // The paper file number staff actually use. Continues the legacy
            // series so historical folders keep matching.
            $table->unsignedBigInteger('code')->unique();

            $table->string('first_name');
            $table->string('last_name');
            $table->string('father_name')->nullable();

            // Real dates. The legacy app stored Jalali strings like '1360/04/12'.
            $table->date('birth_date')->nullable();
            $table->date('registered_on');

            $table->char('gender', 1)->nullable();          // m | f
            $table->char('national_code', 10)->nullable();
            $table->string('mobile', 15)->nullable();
            $table->string('home_phone', 20)->nullable();
            $table->string('work_phone', 20)->nullable();
            $table->string('home_address')->nullable();
            $table->string('work_address')->nullable();
            $table->string('job')->nullable();
            $table->string('referrer_name')->nullable();

            // CodeZonkan — the physical binder the paper record lives in.
            $table->string('binder_code', 30)->nullable();

            // Khowlage / Description / Molahezat in the legacy schema.
            $table->text('medical_summary')->nullable();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('insurance_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();

            $table->index('last_name');
            $table->index('mobile');
            $table->index('national_code');
            $table->index('registered_on');
        });

        $this->addTrigramIndexes();
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }

    /**
     * Trigram indexes make "search as you type" patient lookup fast on names,
     * which is the single most used screen in the clinic. PostgreSQL only.
     */
    private function addTrigramIndexes(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement(
            "CREATE INDEX patients_name_trgm_idx ON patients USING gin ((first_name || ' ' || last_name) gin_trgm_ops)"
        );
        DB::statement('CREATE INDEX patients_mobile_trgm_idx ON patients USING gin (mobile gin_trgm_ops)');
    }
};
