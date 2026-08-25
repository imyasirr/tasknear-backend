<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payments') && ! Schema::hasColumn('payments', 'gateway_order_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('gateway_order_id')->nullable()->after('gateway');
            });
        }

        if (! Schema::hasTable('checkout_sessions')) {
            Schema::create('checkout_sessions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('purpose', 32);
                $table->unsignedBigInteger('reference_id');
                $table->string('gateway_order_id')->unique();
                $table->unsignedInteger('amount_inr');
                $table->string('status', 32)->default('pending');
                $table->json('meta')->nullable();
                $table->dateTime('expires_at')->nullable();
                $table->dateTime('completed_at')->nullable();
                $table->timestamps();

                $table->index(['purpose', 'reference_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_sessions');

        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'gateway_order_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn('gateway_order_id');
            });
        }
    }
};
