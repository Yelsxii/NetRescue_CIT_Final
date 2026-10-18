<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('agent_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->foreignId('incident_id')->nullable()->constrained('incidents')->nullOnDelete();
            $table->foreignId('recovery_action_id')->nullable()->constrained('recovery_actions')->nullOnDelete();
            $table->string('command_code', 64)->index();
            $table->json('payload')->nullable();
            $table->string('status', 24)->default('queued')->index(); // queued, dispatched, completed, failed, expired
            $table->dateTime('queued_at');
            $table->dateTime('dispatched_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('expires_at')->nullable()->index();
            $table->text('result_output')->nullable();
            $table->text('result_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_commands');
    }
};
