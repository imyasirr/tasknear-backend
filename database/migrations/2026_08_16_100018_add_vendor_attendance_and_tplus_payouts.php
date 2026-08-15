<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('check_in_otp', 8);
            $table->string('check_out_otp', 8);
            $table->timestamp('check_in_at')->nullable();
            $table->timestamp('check_out_at')->nullable();
            $table->timestamps();
        });

        Schema::table('payouts', function (Blueprint $table) {
            $table->foreignId('service_request_id')->nullable()->after('assignment_id')->constrained()->nullOnDelete();
            $table->timestamp('due_at')->nullable()->after('paid_at');
        });

        Schema::table('caterer_profiles', function (Blueprint $table) {
            $table->string('upi_vpa')->nullable()->after('gstin');
        });
    }

    public function down(): void
    {
        Schema::table('caterer_profiles', function (Blueprint $table) {
            $table->dropColumn('upi_vpa');
        });
        Schema::table('payouts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_request_id');
            $table->dropColumn('due_at');
        });
        Schema::dropIfExists('vendor_attendances');
    }
};
