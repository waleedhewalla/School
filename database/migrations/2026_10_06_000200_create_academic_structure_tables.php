<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_current')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['school_id', 'name']);
        });

        Schema::create('terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->unsignedTinyInteger('sequence');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamps();
            $table->unique(['academic_year_id', 'sequence']);
        });

        Schema::create('stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->unsignedTinyInteger('sequence');
            $table->timestamps();
            $table->unique(['school_id', 'code']);
        });

        Schema::create('grade_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stage_id')->constrained()->cascadeOnDelete();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->unsignedTinyInteger('sequence');
            $table->timestamps();
        });

        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grade_level_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['academic_year_id', 'grade_level_id', 'name']);
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['school_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('grade_levels');
        Schema::dropIfExists('stages');
        Schema::dropIfExists('terms');
        Schema::dropIfExists('academic_years');
    }
};
