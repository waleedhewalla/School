<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 5)->nullable()->after('email');
            $table->boolean('is_platform_admin')->default(false)->after('locale');
        });

        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('country', 2)->default('SA');
            $table->string('timezone')->default('Asia/Riyadh');
            $table->string('default_locale', 5)->default('ar');
            $table->string('date_display', 10)->default('both');
            $table->string('ministry_code')->nullable()->comment('Ministry of Education school number (Noor)');
            $table->string('vat_number', 15)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('campuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('gender', 10)->default('mixed');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['user_id', 'school_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('campuses');
        Schema::dropIfExists('schools');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['locale', 'is_platform_admin']);
        });
    }
};
