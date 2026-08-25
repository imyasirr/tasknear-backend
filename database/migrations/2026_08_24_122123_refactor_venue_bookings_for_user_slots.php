<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds booking-range columns if an older venue schema already ran without them.
 * Safe on MySQL (no ->change(), uses dateTime, hasColumn guards).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('venues') && ! Schema::hasColumn('venues', 'price_per_day_inr')) {
            Schema::table('venues', function (Blueprint $table) {
                $table->unsignedInteger('price_per_day_inr')->default(25000);
            });
        }

        if (! Schema::hasTable('venue_bookings')) {
            return;
        }

        if (! Schema::hasColumn('venue_bookings', 'starts_at')) {
            Schema::table('venue_bookings', function (Blueprint $table) {
                $table->dateTime('starts_at')->nullable();
            });
        }
        if (! Schema::hasColumn('venue_bookings', 'ends_at')) {
            Schema::table('venue_bookings', function (Blueprint $table) {
                $table->dateTime('ends_at')->nullable();
            });
        }
        if (! Schema::hasColumn('venue_bookings', 'booked_by_partner')) {
            Schema::table('venue_bookings', function (Blueprint $table) {
                $table->boolean('booked_by_partner')->default(false);
            });
        }
        if (! Schema::hasColumn('venue_bookings', 'customer_name')) {
            Schema::table('venue_bookings', function (Blueprint $table) {
                $table->string('customer_name')->nullable();
            });
        }
        if (! Schema::hasColumn('venue_bookings', 'customer_phone')) {
            Schema::table('venue_bookings', function (Blueprint $table) {
                $table->string('customer_phone', 20)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('venue_bookings')) {
            $cols = array_values(array_filter([
                Schema::hasColumn('venue_bookings', 'starts_at') ? 'starts_at' : null,
                Schema::hasColumn('venue_bookings', 'ends_at') ? 'ends_at' : null,
                Schema::hasColumn('venue_bookings', 'booked_by_partner') ? 'booked_by_partner' : null,
                Schema::hasColumn('venue_bookings', 'customer_name') ? 'customer_name' : null,
                Schema::hasColumn('venue_bookings', 'customer_phone') ? 'customer_phone' : null,
            ]));
            if ($cols !== []) {
                Schema::table('venue_bookings', function (Blueprint $table) use ($cols) {
                    $table->dropColumn($cols);
                });
            }
        }

        if (Schema::hasTable('venues') && Schema::hasColumn('venues', 'price_per_day_inr')) {
            Schema::table('venues', function (Blueprint $table) {
                $table->dropColumn('price_per_day_inr');
            });
        }
    }
};
