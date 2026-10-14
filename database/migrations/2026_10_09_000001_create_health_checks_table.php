<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_checks', function (Blueprint $table) {
            $table->id();
            $table->dateTime('checked_at')->index();
            $table->string('source', 32)->default('manual')->index();
            $table->boolean('platform_supported')->default(true);
            $table->string('interface_name')->nullable();
            $table->string('local_ip', 45)->nullable();
            $table->string('gateway_ip', 45)->nullable();
            $table->json('dns_servers')->nullable();
            $table->boolean('gateway_reachable')->default(false);
            $table->decimal('gateway_latency_ms', 8, 2)->nullable();
            $table->boolean('internet_reachable')->default(false);
            $table->decimal('internet_latency_ms', 8, 2)->nullable();
            $table->boolean('dns_operational')->default(false);
            $table->decimal('dns_latency_ms', 8, 2)->nullable();
            $table->string('dns_test_domain')->nullable();
            $table->unsignedTinyInteger('health_score')->default(0);
            $table->string('health_status', 32)->index();
            $table->string('diagnosis_code', 64)->index();
            $table->string('diagnosis_title');
            $table->string('severity', 32)->index();
            $table->text('diagnosis_summary')->nullable();
            $table->json('diagnosis_details')->nullable();
            $table->json('probe_meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_checks');
    }
};
