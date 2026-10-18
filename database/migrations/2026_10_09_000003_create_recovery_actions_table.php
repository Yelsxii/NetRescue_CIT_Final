<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('recovery_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained()->cascadeOnDelete();
            $table->string('action_code', 64)->index();
            $table->string('action_label');
            $table->foreignId('before_health_check_id')->nullable()->constrained('health_checks')->nullOnDelete();
            $table->foreignId('after_health_check_id')->nullable()->constrained('health_checks')->nullOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('completed_at')->nullable();
            $table->boolean('success')->nullable();
            $table->text('output_excerpt')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recovery_actions');
    }
};
