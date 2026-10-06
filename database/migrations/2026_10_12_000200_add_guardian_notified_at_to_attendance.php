<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Set once a guardian has been alerted for this mark, so flipping a
        // code back and forth can't send a stream of paid messages.
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->timestamp('guardian_notified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn('guardian_notified_at');
        });
    }
};
