<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The school's bell schedule: lesson periods and breaks, in order.
        Schema::create('periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence');
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->time('starts_at');
            $table->time('ends_at');
            $table->boolean('is_break')->default(false);
            $table->timestamps();
            $table->unique(['school_id', 'sequence']);
        });

        // One lesson in a section's week. day: 0 = Sunday … 6 = Saturday.
        Schema::create('timetable_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teaching_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_member_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day');
            $table->string('room', 30)->nullable();
            $table->timestamps();
            $table->unique(['section_id', 'day', 'period_id']);
            // A teacher can't be in two places at once.
            $table->unique(['academic_year_id', 'staff_member_id', 'day', 'period_id'], 'timetable_teacher_slot_unique');
        });

        Schema::table('schools', function (Blueprint $table) {
            $table->json('school_days')->nullable()->comment('0 = Sunday … 6 = Saturday');
            $table->json('notification_settings')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn(['school_days', 'notification_settings']);
        });
        Schema::dropIfExists('timetable_entries');
        Schema::dropIfExists('periods');
    }
};
