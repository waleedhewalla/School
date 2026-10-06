<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One window per grade and academic year: when applications are open,
        // how many seats, and which birth dates fit the grade that year.
        Schema::create('admission_windows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grade_level_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('seats');
            $table->date('opens_on');
            $table->date('closes_on');
            $table->date('born_from')->nullable();
            $table->date('born_to')->nullable();
            $table->unsignedSmallInteger('exception_days')->default(0)->comment('younger by up to N days: staff decision');
            $table->timestamps();
            $table->unique(['academic_year_id', 'grade_level_id']);
        });

        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admission_window_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference', 20);
            $table->string('token_hash', 64)->unique();
            $table->text('token')->comment('encrypted, so later messages can repeat the link');
            $table->string('status', 20)->default('submitted');
            // Student
            $table->string('first_name_ar');
            $table->string('father_name_ar')->nullable();
            $table->string('grandfather_name_ar')->nullable();
            $table->string('family_name_ar');
            $table->string('name_en')->nullable();
            $table->string('gender', 6);
            $table->date('date_of_birth');
            $table->string('national_id', 10)->nullable();
            $table->string('nationality', 2)->default('SA');
            $table->string('current_school')->nullable();
            $table->boolean('from_private_school')->default(false);
            $table->boolean('has_sibling')->default(false);
            // Guardian
            $table->string('guardian_name');
            $table->string('guardian_national_id', 10)->nullable();
            $table->string('guardian_phone', 20);
            $table->string('guardian_email')->nullable();
            $table->string('guardian_relationship', 20)->default('father');
            // Process
            $table->string('age_check', 12)->comment('ok, exception, outside');
            $table->timestamp('assessment_at')->nullable();
            $table->text('staff_note')->nullable();
            $table->boolean('noor_transfer_done')->default(false);
            $table->timestamp('consented_at');
            $table->timestamp('submitted_at');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'reference']);
            $table->index(['school_id', 'status']);
        });

        Schema::create('application_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('path');
            $table->string('original_name');
            $table->string('status', 10)->default('pending');
            $table->string('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('application_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_events');
        Schema::dropIfExists('application_documents');
        Schema::dropIfExists('applications');
        Schema::dropIfExists('admission_windows');
    }
};
