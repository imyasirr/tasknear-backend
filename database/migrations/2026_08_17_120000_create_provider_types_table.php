<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('role')->unique();
            $table->string('match_mode', 16)->default('vendor');
            $table->string('name');
            $table->string('name_hi')->nullable();
            $table->text('description')->nullable();
            $table->text('description_hi')->nullable();
            $table->json('category_slugs')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_types');
    }
};
