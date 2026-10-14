<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitor_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('interval_minutes')->default(5);
            $table->unsignedSmallInteger('latency_warning_ms')->default(100);
            $table->string('dns_test_domain')->default('example.com');
            $table->json('internet_targets')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitor_settings');
    }
};
