<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('state')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('cities')->insert([
            ['name' => 'Lucknow', 'state' => 'Uttar Pradesh', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::table('worker_profiles', function (Blueprint $table) {
            $table->string('pan_number', 16)->nullable()->after('upi_vpa');
            $table->string('aadhaar_number', 16)->nullable()->after('pan_number');
            $table->string('bank_account_name')->nullable()->after('aadhaar_number');
            $table->string('bank_account_number', 32)->nullable()->after('bank_account_name');
            $table->string('bank_ifsc', 16)->nullable()->after('bank_account_number');
            $table->string('bank_name')->nullable()->after('bank_ifsc');
        });

        Schema::table('task_details', function (Blueprint $table) {
            $table->unsignedInteger('rate_per_worker_inr')->nullable()->after('duration_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('task_details', function (Blueprint $table) {
            $table->dropColumn('rate_per_worker_inr');
        });
        Schema::table('worker_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'pan_number',
                'aadhaar_number',
                'bank_account_name',
                'bank_account_number',
                'bank_ifsc',
                'bank_name',
            ]);
        });
        Schema::dropIfExists('cities');
    }
};
