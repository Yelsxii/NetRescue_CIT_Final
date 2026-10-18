<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->boolean('recognized')->default(false)->index();
        });

        DB::table('devices')->where('is_local', true)->update(['recognized' => true]);
        DB::table('devices')->where('connection_type', 'agent')->update(['recognized' => true]);
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn('recognized');
        });
    }
};
