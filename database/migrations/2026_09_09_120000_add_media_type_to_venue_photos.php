<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('venue_photos') && ! Schema::hasColumn('venue_photos', 'media_type')) {
            Schema::table('venue_photos', function (Blueprint $table) {
                $table->string('media_type', 16)->default('image')->after('path');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('venue_photos') && Schema::hasColumn('venue_photos', 'media_type')) {
            Schema::table('venue_photos', function (Blueprint $table) {
                $table->dropColumn('media_type');
            });
        }
    }
};
