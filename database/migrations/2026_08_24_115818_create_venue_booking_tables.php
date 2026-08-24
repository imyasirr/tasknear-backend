<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_partner_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('company_name');
            $table->text('bio')->nullable();
            $table->string('city', 80)->nullable();
            $table->string('gstin', 32)->nullable();
            $table->string('upi_vpa')->nullable();
            $table->string('status', 32)->default('active');
            $table->timestamps();
        });

        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('venue_type', 32)->default('banquet');
            $table->text('description')->nullable();
            $table->string('address')->nullable();
            $table->string('city', 80)->nullable();
            $table->unsignedSmallInteger('capacity_min')->default(50);
            $table->unsignedSmallInteger('capacity_max')->default(500);
            $table->unsignedTinyInteger('advance_percent')->default(30);
            $table->json('amenities')->nullable();
            $table->string('status', 32)->default('draft');
            $table->timestamps();
        });

        Schema::create('venue_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('venue_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedInteger('price_inr');
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->string('status', 32)->default('open');
            $table->timestamps();

            $table->index(['venue_id', 'starts_at']);
        });

        Schema::create('venue_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('slot_id')->constrained('venue_slots')->cascadeOnDelete();
            $table->foreignId('customer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('partner_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('guest_count');
            $table->unsignedInteger('total_inr');
            $table->unsignedInteger('advance_inr');
            $table->unsignedInteger('balance_inr')->default(0);
            $table->text('notes')->nullable();
            $table->string('status', 32)->default('awaiting_payment');
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_bookings');
        Schema::dropIfExists('venue_slots');
        Schema::dropIfExists('venue_photos');
        Schema::dropIfExists('venues');
        Schema::dropIfExists('venue_partner_profiles');
    }
};
