<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('emotional_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('companion');
            $table->string('recognized_emotion')->nullable();
            $table->integer('depression_score');
            $table->integer('anxiety_score');
            $table->integer('stress_score');
            $table->string('depression_severity');
            $table->string('anxiety_severity');
            $table->string('stress_severity');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emotional_histories');
    }
};
