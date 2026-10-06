<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Grades 1–2 are assessed differently from the rest of primary, so a
        // scale can also apply to a single grade.
        Schema::table('grading_scales', function (Blueprint $table) {
            $table->foreignId('grade_level_id')->nullable()->after('stage_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('grading_scales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('grade_level_id');
        });
    }
};
