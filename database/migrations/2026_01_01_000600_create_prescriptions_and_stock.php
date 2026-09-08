<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('prescribed_on');
            $table->text('notes')->nullable();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'prescribed_on']);
        });

        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('drug_variant_id')->constrained()->restrictOnDelete();
            $table->string('dosage')->nullable();        // "۱ عدد، هر ۸ ساعت"
            $table->string('instructions')->nullable();  // "بعد از غذا"
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unsignedSmallInteger('item_order')->default(0);
            $table->timestamps();

            $table->index('prescription_id');
        });

        // tblStore — consumables held in the clinic.
        Schema::create('stock_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('unit', 30)->nullable();      // عدد، بسته، میلی‌لیتر
            $table->integer('reorder_level')->default(0);
            $table->unsignedSmallInteger('item_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
        });

        /*
         | tblStoreUsage. Stock on hand is derived by summing movements rather
         | than kept in a mutable column — an append-only ledger cannot drift
         | out of step with its own history.
         */
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->char('direction', 3);                 // in | out
            $table->unsignedInteger('quantity');
            $table->date('moved_on');
            $table->text('description')->nullable();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->timestamps();

            $table->index(['stock_item_id', 'moved_on']);
            $table->index('moved_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_items');
        Schema::dropIfExists('prescription_items');
        Schema::dropIfExists('prescriptions');
    }
};
