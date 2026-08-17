<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->string('provider_type', 32)->default('caterer')->after('type');
        });

        Schema::table('task_details', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('service_request_id')->constrained('categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('task_details', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });

        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropColumn('provider_type');
        });
    }
};
