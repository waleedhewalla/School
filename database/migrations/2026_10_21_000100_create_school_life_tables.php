<?php

use App\Actions\Schools\ProvisionSchoolRoles;
use App\Models\BehaviourCategory;
use App\Models\School;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homework', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 200);
            $table->text('body')->nullable();
            $table->date('due_on');
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'section_id', 'due_on']);
        });

        // Behaviour types per school: negative ones by degree with points
        // deducted, positive ones with points added back.
        Schema::create('behaviour_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('kind', 10)->comment('positive or negative');
            $table->unsignedTinyInteger('degree')->nullable();
            $table->unsignedSmallInteger('points');
            $table->boolean('notify_guardian')->default(false);
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sequence')->default(0);
            $table->timestamps();
        });

        Schema::create('behaviour_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('behaviour_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('occurred_on');
            $table->text('note')->nullable();
            $table->string('action_taken', 300)->nullable();
            $table->timestamp('guardian_notified_at')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'student_id', 'academic_year_id']);
        });

        // A guardian's request to excuse a child's absence (e.g. a medical report).
        Schema::create('absence_excuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('from_date');
            $table->date('to_date');
            $table->text('reason');
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('status', 10)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 300)->nullable();
            $table->timestamps();
            $table->index(['school_id', 'status']);
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_member_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->date('from_date');
            $table->date('to_date');
            $table->text('reason')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('status', 10)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 300)->nullable();
            $table->timestamps();
            $table->index(['school_id', 'status']);
        });

        ProvisionSchoolRoles::grantMissingDefaults();

        // Existing schools get the starting behaviour categories.
        School::query()->withTrashed()->each(fn (School $school) => app(CurrentSchool::class)
            ->run($school, fn () => BehaviourCategory::query()->exists() ?: BehaviourCategory::seedDefaults()));
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('absence_excuses');
        Schema::dropIfExists('behaviour_incidents');
        Schema::dropIfExists('behaviour_categories');
        Schema::dropIfExists('homework');
    }
};
