<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // tblOpration — a single billable treatment performed on a patient.
        Schema::create('treatments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('treatment_service_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->date('performed_on');
            // Rial. Copied from the service tariff at creation time so later
            // tariff changes never rewrite history.
            $table->unsignedBigInteger('amount');
            $table->text('description')->nullable();

            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'performed_on']);
            $table->index('performed_on');
        });

        /*
         | Replaces the legacy comma-separated `ToothName` string
         | ("_3UR,_EUL,_6LL"). Tooth codes use FDI two-digit notation:
         |   permanent 11-18 21-28 31-38 41-48
         |   primary   51-55 61-65 71-75 81-85
         */
        Schema::create('treatment_teeth', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treatment_id')->constrained()->cascadeOnDelete();
            $table->char('tooth_code', 2);
            $table->timestamps();

            $table->unique(['treatment_id', 'tooth_code']);
            $table->index('tooth_code');
        });

        // tblPay — money received from a patient.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('payment_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->date('paid_on');
            $table->time('paid_at')->nullable();

            $table->unsignedBigInteger('amount');       // Rial actually received
            $table->unsignedBigInteger('discount')->default(0); // Takhfif — written off
            $table->text('description')->nullable();

            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'paid_on']);
            $table->index(['paid_on', 'payment_type_id']);
        });

        // tblVisit — the daily sequential visit number printed on the slip.
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->date('visit_date')->unique();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });

        // Not in the legacy app. A clinic web app needs a diary, and the
        // dashboard/SMS reminders below depend on it.
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('treatment_service_id')->nullable()->constrained()->nullOnDelete();

            $table->date('scheduled_on');
            $table->time('starts_at');
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->string('status', 20)->default('scheduled'); // scheduled|confirmed|done|cancelled|no_show
            $table->text('notes')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['scheduled_on', 'starts_at']);
            $table->index(['patient_id', 'scheduled_on']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('visits');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('treatment_teeth');
        Schema::dropIfExists('treatments');
    }
};
