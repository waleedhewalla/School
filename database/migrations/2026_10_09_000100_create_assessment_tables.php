<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Grade bands, e.g. ممتاز from 90%. A school has one or more scales.
        Schema::create('grading_scales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->decimal('pass_percent', 5, 2)->default(50);
            $table->timestamps();
        });

        Schema::create('grading_bands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grading_scale_id')->constrained()->cascadeOnDelete();
            $table->decimal('min_percent', 5, 2);
            $table->string('label_ar');
            $table->string('label_en')->nullable();
            $table->timestamps();
            $table->unique(['grading_scale_id', 'min_percent']);
        });

        // What makes up a subject's term mark: homework 20%, final exam 50% …
        Schema::create('assessment_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grade_level_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->decimal('max_score', 6, 2);
            $table->decimal('weight', 5, 2)->comment('percent of the subject mark');
            $table->unsignedTinyInteger('sequence')->default(0);
            $table->timestamps();
            $table->index(['term_id', 'grade_level_id', 'subject_id']);
        });

        Schema::create('assessment_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_component_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 6, 2)->nullable();
            $table->boolean('is_absent')->default(false);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['assessment_component_id', 'student_id']);
        });

        Schema::table('terms', function (Blueprint $table) {
            $table->boolean('marks_open')->default(true);
            $table->timestamp('results_published_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('terms', function (Blueprint $table) {
            $table->dropColumn(['marks_open', 'results_published_at']);
        });
        Schema::dropIfExists('assessment_scores');
        Schema::dropIfExists('assessment_components');
        Schema::dropIfExists('grading_bands');
        Schema::dropIfExists('grading_scales');
    }
};
