<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->uuid('device_uuid')->unique();
            $table->string('display_name');
            $table->string('hostname')->nullable();
            $table->string('connection_type', 24)->default('agent')->index(); // local | agent
            $table->boolean('is_local')->default(false)->index();
            $table->string('operating_system')->nullable();
            $table->string('os_version')->nullable();
            $table->string('agent_version')->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->string('adapter_name')->nullable();
            $table->string('gateway_ip', 45)->nullable();
            $table->json('dns_servers')->nullable();
            $table->string('status', 24)->default('unknown')->index(); // healthy, degraded, critical, offline, unknown
            $table->unsignedTinyInteger('health_score')->nullable();
            $table->dateTime('last_seen_at')->nullable()->index();
            $table->dateTime('last_health_check_at')->nullable()->index();
            $table->string('auth_token_hash', 64)->nullable()->unique();
            $table->dateTime('registered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
