<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** An invitation can be for a guardian's or a student's portal login; accepting links the account. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->foreignId('guardian_id')->nullable()->after('roles')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->after('guardian_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('student_id');
            $table->dropConstrainedForeignId('guardian_id');
        });
    }
};
