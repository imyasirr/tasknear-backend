<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('venue_name')->nullable();
            $table->unsignedInteger('guest_count')->nullable();
            $table->string('dress_code')->nullable();
            $table->boolean('meal_included')->default(false);
            $table->timestamps();
        });

        Schema::create('event_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_detail_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('headcount');
            $table->timestamp('start_at');
            $table->timestamp('end_at');
            $table->unsignedInteger('rate_per_worker_inr');
            $table->string('status', 32)->default('filling');
            $table->timestamps();
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->foreign('event_shift_id')->references('id')->on('event_shifts')->nullOnDelete();
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('check_in_otp', 8)->nullable();
            $table->string('check_out_otp', 8)->nullable();
            $table->timestamp('check_in_at')->nullable();
            $table->timestamp('check_out_at')->nullable();
            $table->timestamps();
        });

        Schema::create('replacements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('old_assignment_id')->constrained('assignments')->cascadeOnDelete();
            $table->foreignId('new_assignment_id')->nullable()->constrained('assignments')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('replacements');
        Schema::dropIfExists('attendances');
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropForeign(['event_shift_id']);
        });
        Schema::dropIfExists('event_shifts');
        Schema::dropIfExists('event_details');
    }
};
