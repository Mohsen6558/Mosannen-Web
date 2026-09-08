<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * tblImages. The legacy app kept only a filename and read the pixels from the
 * SMB share \\server\AppIMG\. Here the file lives on a configurable disk and
 * is always served through an authorised controller, never a public URL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('radiographs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->date('taken_on');
            $table->string('subject')->nullable();

            $table->string('disk', 30)->default('images');
            $table->string('path');                       // random, unguessable
            $table->string('original_name')->nullable();  // what the operator saw
            $table->string('thumbnail_path')->nullable();
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->string('checksum', 64)->nullable();   // sha256, de-duplicates re-imports

            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id', 'taken_on']);
            $table->index('taken_on');
            $table->index('checksum');
        });

        Schema::create('radiograph_teeth', function (Blueprint $table) {
            $table->id();
            $table->foreignId('radiograph_id')->constrained()->cascadeOnDelete();
            $table->char('tooth_code', 2);
            $table->timestamps();

            $table->unique(['radiograph_id', 'tooth_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('radiograph_teeth');
        Schema::dropIfExists('radiographs');
    }
};
