<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Look-up tables the clinic maintains itself: insurers, payment methods,
 * the treatment catalogue and the drug catalogue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurances', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('item_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            $table->index(['is_active', 'item_order']);
        });

        Schema::create('payment_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('item_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            $table->index(['is_active', 'item_order']);
        });

        // tblTitle — treatment groups (e.g. ترمیمی, جراحی)
        Schema::create('treatment_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('item_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });

        // tblSubtitles — the billable services, each with a tariff.
        Schema::create('treatment_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treatment_category_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name');
            // Rial, always a whole number. Never a float.
            $table->unsignedBigInteger('price')->default(0);
            $table->unsignedSmallInteger('item_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            $table->index(['treatment_category_id', 'item_order']);
        });

        // tblDrag — drug groups
        Schema::create('drugs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('item_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });

        // tblSubDrag — the prescribable forms/doses of each drug
        Schema::create('drug_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drug_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name');
            $table->string('default_dosage')->nullable();
            $table->string('default_instructions')->nullable();
            $table->unsignedSmallInteger('item_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            $table->index(['drug_id', 'item_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drug_variants');
        Schema::dropIfExists('drugs');
        Schema::dropIfExists('treatment_services');
        Schema::dropIfExists('treatment_categories');
        Schema::dropIfExists('payment_types');
        Schema::dropIfExists('insurances');
    }
};
