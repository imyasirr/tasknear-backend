<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement('UPDATE users SET locale = "en"');
        } else {
            DB::table('users')->update(['locale' => 'en']);
        }
    }

    public function down(): void
    {
        //
    }
};
