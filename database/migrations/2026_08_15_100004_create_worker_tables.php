<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worker_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('photo_path')->nullable();
            $table->text('bio')->nullable();
            $table->string('city')->nullable();
            $table->unsignedTinyInteger('service_radius_km')->default(10);
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('jobs_completed')->default(0);
            $table->unsignedTinyInteger('reliability_score')->default(80);
            $table->string('status', 32)->default('pending_kyc');
            $table->boolean('is_available')->default(false);
            $table->string('upi_vpa')->nullable();
            $table->timestamps();
        });

        Schema::create('worker_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_profile_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('path');
            $table->string('status', 32)->default('pending');
            $table->string('review_note')->nullable();
            $table->timestamps();
        });

        Schema::create('worker_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('experience_years')->default(0);
            $table->timestamps();
            $table->unique(['worker_profile_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_skills');
        Schema::dropIfExists('worker_documents');
        Schema::dropIfExists('worker_profiles');
    }
};
