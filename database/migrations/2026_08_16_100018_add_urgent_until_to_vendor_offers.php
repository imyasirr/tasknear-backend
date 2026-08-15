<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_offers', function (Blueprint $table) {
            $table->timestamp('urgent_until')->nullable()->after('invited_at');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_offers', function (Blueprint $table) {
            $table->dropColumn('urgent_until');
        });
    }
};
