<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->index();
            $table->string('title');
            $table->string('severity', 32)->index();
            $table->string('status', 32)->default('active')->index();
            $table->dateTime('first_detected_at');
            $table->dateTime('last_detected_at');
            $table->dateTime('resolved_at')->nullable();
            $table->unsignedInteger('occurrence_count')->default(1);
            $table->foreignId('latest_health_check_id')->nullable()->constrained('health_checks')->nullOnDelete();
            $table->text('summary')->nullable();
            $table->string('recommended_action')->nullable();
            $table->string('recovery_action_code', 64)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
