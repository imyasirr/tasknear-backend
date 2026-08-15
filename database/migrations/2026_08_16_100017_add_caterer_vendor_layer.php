<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->foreignId('vendor_user_id')->nullable()->after('requester_id')->constrained('users')->nullOnDelete();
        });

        Schema::create('caterer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('company_name');
            $table->text('bio')->nullable();
            $table->string('city')->nullable();
            $table->string('gstin', 20)->nullable();
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('jobs_completed')->default(0);
            $table->string('status', 32)->default('active');
            $table->boolean('is_available')->default(true);
            $table->timestamps();
        });

        Schema::create('caterer_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caterer_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['caterer_profile_id', 'category_id']);
        });

        Schema::create('vendor_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('caterer_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 32)->default('invited');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->index(['caterer_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_offers');
        Schema::dropIfExists('caterer_skills');
        Schema::dropIfExists('caterer_profiles');

        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vendor_user_id');
        });
    }
};
