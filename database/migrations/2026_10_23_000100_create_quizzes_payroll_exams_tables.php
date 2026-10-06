<?php

use App\Actions\Schools\ProvisionSchoolRoles;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Online quizzes, staff contracts and payroll, and external exams (Nafes, Qiyas). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 200);
            $table->text('instructions')->nullable();
            $table->timestamp('opens_at');
            $table->timestamp('closes_at');
            $table->unsignedSmallInteger('time_limit_minutes')->nullable();
            $table->boolean('published')->default(false);
            $table->boolean('show_results')->default(true);
            $table->timestamps();
            $table->index(['school_id', 'section_id', 'opens_at']);
        });

        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->string('type', 12)->comment('single, multiple, true_false, short');
            $table->text('body');
            $table->json('options')->nullable();
            $table->json('correct');
            $table->decimal('points', 5, 2)->default(1);
            $table->unsignedSmallInteger('sequence')->default(0);
            $table->timestamps();
        });

        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->json('answers')->nullable();
            $table->decimal('score', 6, 2)->nullable();
            $table->decimal('max_score', 6, 2)->nullable();
            $table->timestamps();
            $table->unique(['quiz_id', 'student_id']);
        });

        Schema::create('staff_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_member_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('is_saudi')->default(true);
            $table->date('hired_on')->nullable();
            $table->decimal('basic_salary', 10, 2);
            $table->decimal('housing_allowance', 10, 2)->default(0);
            $table->decimal('transport_allowance', 10, 2)->default(0);
            $table->decimal('other_allowances', 10, 2)->default(0);
            $table->boolean('gosi_registered')->default(true);
            $table->string('bank', 100)->nullable();
            $table->string('iban', 34)->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('month', 7)->comment('YYYY-MM');
            $table->string('status', 10)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'month']);
        });

        Schema::create('payroll_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_member_id')->constrained()->cascadeOnDelete();
            $table->decimal('basic', 10, 2);
            $table->decimal('housing', 10, 2);
            $table->decimal('transport', 10, 2);
            $table->decimal('other', 10, 2);
            $table->decimal('additions', 10, 2)->default(0);
            $table->decimal('deductions', 10, 2)->default(0);
            $table->string('note', 200)->nullable();
            $table->decimal('gosi_employee', 10, 2);
            $table->decimal('gosi_employer', 10, 2);
            $table->decimal('net', 10, 2);
            $table->timestamps();
            $table->unique(['payroll_run_id', 'staff_member_id']);
        });

        Schema::create('external_exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grade_level_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20)->comment('nafes, tahsili, qudrat, other');
            $table->string('name');
            $table->date('held_on')->nullable();
            $table->decimal('max_score', 6, 2)->default(100);
            $table->timestamps();
        });

        Schema::create('external_exam_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('external_exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 6, 2);
            $table->timestamps();
            $table->unique(['external_exam_id', 'student_id']);
        });

        ProvisionSchoolRoles::grantMissingDefaults();
    }

    public function down(): void
    {
        foreach (['external_exam_results', 'external_exams', 'payroll_lines', 'payroll_runs', 'staff_contracts', 'quiz_attempts', 'quiz_questions', 'quizzes'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
