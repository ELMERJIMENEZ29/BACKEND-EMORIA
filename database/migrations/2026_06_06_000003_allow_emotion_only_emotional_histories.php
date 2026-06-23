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
        Schema::table('emotional_histories', function (Blueprint $table) {
            $table->string('companion')->default('emotion-model')->change();
            $table->integer('depression_score')->nullable()->change();
            $table->integer('anxiety_score')->nullable()->change();
            $table->integer('stress_score')->nullable()->change();
            $table->string('depression_severity')->nullable()->change();
            $table->string('anxiety_severity')->nullable()->change();
            $table->string('stress_severity')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('emotional_histories', function (Blueprint $table) {
            $table->string('companion')->default(null)->change();
            $table->integer('depression_score')->nullable(false)->change();
            $table->integer('anxiety_score')->nullable(false)->change();
            $table->integer('stress_score')->nullable(false)->change();
            $table->string('depression_severity')->nullable(false)->change();
            $table->string('anxiety_severity')->nullable(false)->change();
            $table->string('stress_severity')->nullable(false)->change();
        });
    }
};
