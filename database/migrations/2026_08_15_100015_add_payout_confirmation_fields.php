<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->timestamp('confirmed_at')->nullable()->after('paid_at');
            $table->timestamp('disputed_at')->nullable()->after('confirmed_at');
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->foreignId('payout_id')->nullable()->after('service_request_id')->constrained()->nullOnDelete();
        });

        DB::table('payouts')->where('status', 'released')->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);
        DB::table('payouts')->where('status', 'pending')->update([
            'status' => 'sent',
        ]);
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payout_id');
        });

        Schema::table('payouts', function (Blueprint $table) {
            $table->dropColumn(['confirmed_at', 'disputed_at']);
        });
    }
};
