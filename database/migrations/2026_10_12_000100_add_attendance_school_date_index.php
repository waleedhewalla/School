<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dashboard and report queries filter a school's daily register by date range.
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->index(['school_id', 'period', 'date']);
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropIndex(['school_id', 'period', 'date']);
        });
    }
};
