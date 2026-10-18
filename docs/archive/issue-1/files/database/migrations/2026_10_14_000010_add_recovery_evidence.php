<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->foreignId('opening_health_check_id')->nullable()->constrained('health_checks')->nullOnDelete();
            $table->foreignId('closing_health_check_id')->nullable()->constrained('health_checks')->nullOnDelete();
            $table->string('resolution_kind', 32)->nullable();
        });
        Schema::table('recovery_actions', function (Blueprint $table) {
            $table->string('execution_status', 32)->nullable();
            $table->string('verification_status', 32)->nullable();
            $table->integer('command_exit_code')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('recovery_actions', function (Blueprint $table) {
            $table->dropColumn(['execution_status', 'verification_status', 'command_exit_code']);
        });
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('opening_health_check_id');
            $table->dropConstrainedForeignId('closing_health_check_id');
            $table->dropColumn('resolution_kind');
        });
    }
};
