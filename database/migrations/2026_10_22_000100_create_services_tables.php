<?php

use App\Actions\Schools\ProvisionSchoolRoles;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** School services: clinic, library, transport and inventory. */
return new class extends Migration
{
    public function up(): void
    {
        // One health card per student (sensitive personal data).
        Schema::create('health_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('blood_type', 3)->nullable();
            $table->text('allergies')->nullable();
            $table->text('chronic_conditions')->nullable();
            $table->text('medications')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('clinic_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('visited_at');
            $table->string('complaint', 300);
            $table->decimal('temperature', 4, 1)->nullable();
            $table->string('outcome', 20)->comment('returned_to_class, rested, sent_home, referred');
            $table->text('treatment')->nullable();
            $table->timestamp('guardian_notified_at')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'visited_at']);
        });

        Schema::create('library_books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('isbn', 20)->nullable();
            $table->string('title');
            $table->string('author')->nullable();
            $table->string('publisher')->nullable();
            $table->unsignedSmallInteger('published_year')->nullable();
            $table->string('category', 100)->nullable();
            $table->string('shelf', 50)->nullable();
            $table->unsignedSmallInteger('copies')->default(1);
            $table->timestamps();
            $table->index(['school_id', 'title']);
        });

        Schema::create('library_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('library_book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('staff_member_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('borrowed_on');
            $table->date('due_on');
            $table->date('returned_on')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['school_id', 'returned_on', 'due_on']);
        });

        Schema::create('buses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('number', 20)->comment('the school\'s bus number');
            $table->string('plate', 20)->nullable();
            $table->unsignedSmallInteger('capacity');
            $table->string('driver_name')->nullable();
            $table->string('driver_phone', 20)->nullable();
            $table->string('supervisor_name')->nullable();
            $table->string('supervisor_phone', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('bus_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bus_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('route_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bus_route_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('sequence')->default(0);
            $table->time('pickup_at')->nullable();
            $table->time('dropoff_at')->nullable();
            $table->timestamps();
        });

        Schema::create('student_transport', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('bus_route_id')->constrained()->cascadeOnDelete();
            $table->foreignId('route_stop_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40)->nullable();
            $table->string('name');
            $table->string('category', 100)->nullable();
            $table->string('location', 100)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('condition', 20)->default('good');
            $table->date('purchased_on')->nullable();
            $table->decimal('unit_value', 12, 2)->nullable();
            $table->foreignId('custodian_id')->nullable()->constrained('staff_members')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'category']);
        });

        ProvisionSchoolRoles::grantMissingDefaults();
    }

    public function down(): void
    {
        foreach (['inventory_items', 'student_transport', 'route_stops', 'bus_routes', 'buses', 'library_loans', 'library_books', 'clinic_visits', 'health_records'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
