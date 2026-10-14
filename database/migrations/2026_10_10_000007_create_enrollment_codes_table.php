<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollment_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->boolean('active')->default(true)->index();
            $table->unsignedSmallInteger('max_uses')->default(50);
            $table->unsignedSmallInteger('uses')->default(0);
            $table->dateTime('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_codes');
    }
};
