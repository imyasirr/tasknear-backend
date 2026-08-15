<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('subscription_features', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name');
            $table->string('name_hi')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_hi')->nullable();
            $table->string('tagline')->nullable();
            $table->unsignedInteger('price_inr');
            $table->unsignedInteger('duration_days');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('subscription_plan_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_feature_id')->constrained()->cascadeOnDelete();
            $table->unique(['subscription_plan_id', 'subscription_feature_id'], 'plan_feature_unique');
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('active');
            $table->unsignedInteger('amount_inr');
            $table->string('gateway', 32)->default('manual');
            $table->string('gateway_payment_id')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->timestamps();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedInteger('labor_inr')->default(0)->after('amount_inr');
            $table->unsignedInteger('commission_inr')->default(0)->after('labor_inr');
            $table->unsignedInteger('commission_bps')->default(0)->after('commission_inr');
            $table->boolean('fee_waived')->default(false)->after('commission_bps');
            $table->foreignId('subscription_id')->nullable()->after('fee_waived')->constrained()->nullOnDelete();
        });

        Schema::table('commissions', function (Blueprint $table) {
            $table->boolean('waived')->default(false)->after('amount_inr');
            $table->foreignId('subscription_id')->nullable()->after('waived')->constrained()->nullOnDelete();
        });

        DB::table('platform_settings')->insert([
            'key' => 'commission_bps',
            'value' => '1500',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subscription_id');
            $table->dropColumn('waived');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subscription_id');
            $table->dropColumn(['labor_inr', 'commission_inr', 'commission_bps', 'fee_waived']);
        });
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('subscription_plan_features');
        Schema::dropIfExists('subscription_plans');
        Schema::dropIfExists('subscription_features');
        Schema::dropIfExists('platform_settings');
    }
};
