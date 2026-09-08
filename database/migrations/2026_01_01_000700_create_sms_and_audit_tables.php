<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Editable message bodies so staff can reword an SMS without a deploy.
        Schema::create('sms_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();   // treatment_created, payment_created, ...
            $table->string('name');
            $table->text('body');                  // supports {{patient}}, {{amount}}, ...
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // tblSMS — the outbox. `status` replaces the legacy Enable flag.
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('mobile', 15);
            $table->text('body');
            $table->string('kind', 40)->default('manual'); // manual|treatment|payment|appointment|bulk
            $table->string('status', 20)->default('queued'); // queued|sent|failed|cancelled
            $table->string('provider', 30)->nullable();
            $table->string('provider_message_id')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('mobile');
            $table->index(['patient_id', 'created_at']);
        });

        /*
         | Audit trail. Patient records and money are regulated data: every
         | mutation needs to be attributable long after the fact. The legacy
         | app had nothing of the sort.
         */
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 40);                    // created|updated|deleted|viewed|printed
            $table->string('subject_type', 120)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('description')->nullable();
            $table->json('properties')->nullable();          // before/after diff
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });

        // Free-form clinic settings editable from the UI.
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 80)->primary();
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string');  // string|int|bool|json
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('sms_messages');
        Schema::dropIfExists('sms_templates');
    }
};
