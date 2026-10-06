<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->unsignedSmallInteger('sequence')->default(100)->after('code');
        });

        // The class teacher's (or principal's) remark on a student's report card.
        Schema::create('report_card_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->text('comment');
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['term_id', 'student_id']);
        });

        // audience: staff, guardians, everyone. With a section, only that section's guardians.
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('audience', 10);
            $table->string('title');
            $table->text('body');
            $table->boolean('send_sms')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('report_card_comments');
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn('sequence');
        });
    }
};
