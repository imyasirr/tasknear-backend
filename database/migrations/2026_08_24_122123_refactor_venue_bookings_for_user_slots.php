<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->unsignedInteger('price_per_day_inr')->default(25000)->after('advance_percent');
        });

        Schema::table('venue_bookings', function (Blueprint $table) {
            $table->timestamp('starts_at')->nullable()->after('slot_id');
            $table->timestamp('ends_at')->nullable()->after('starts_at');
            $table->boolean('booked_by_partner')->default(false)->after('notes');
            $table->string('customer_name')->nullable()->after('booked_by_partner');
            $table->string('customer_phone', 20)->nullable()->after('customer_name');
        });
    }

    public function down(): void
    {
        Schema::table('venue_bookings', function (Blueprint $table) {
            $table->dropColumn(['starts_at', 'ends_at', 'booked_by_partner', 'customer_name', 'customer_phone']);
        });

        Schema::table('venues', function (Blueprint $table) {
            $table->dropColumn('price_per_day_inr');
        });
    }
};
