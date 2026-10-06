<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('code', 5);
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('kind', 10)->comment('present, absent, late or excused');
            $table->boolean('notify_guardian')->default(false);
            $table->boolean('is_default')->default(false);
            $table->unsignedTinyInteger('sequence')->default(0);
            $table->timestamps();
            $table->unique(['school_id', 'code']);
        });

        // period 0 is the daily register; 1..n are lesson periods.
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_code_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date');
            $table->unsignedTinyInteger('period')->default(0);
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'date', 'period']);
            $table->index(['section_id', 'date', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('attendance_codes');
    }
};
