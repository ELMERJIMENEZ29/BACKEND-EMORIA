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
        Schema::table('activity_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('activity_logs', 'user_id')) {
                $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete()->after('id');
            }

            if (!Schema::hasColumn('activity_logs', 'type')) {
                $table->string('type')->nullable()->after('user_id');
            }

            if (!Schema::hasColumn('activity_logs', 'duration_seconds')) {
                $table->integer('duration_seconds')->default(0)->after('type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            if (Schema::hasColumn('activity_logs', 'duration_seconds')) {
                $table->dropColumn('duration_seconds');
            }

            if (Schema::hasColumn('activity_logs', 'type')) {
                $table->dropColumn('type');
            }

            if (Schema::hasColumn('activity_logs', 'user_id')) {
                // drop foreign if exists
                $sm = Schema::getConnection()->getDoctrineSchemaManager();
                $doctrineTable = $sm->listTableDetails('activity_logs');
                if ($doctrineTable->hasForeignKey('activity_logs_user_id_foreign')) {
                    $table->dropForeign('activity_logs_user_id_foreign');
                }
                $table->dropColumn('user_id');
            }
        });
    }
};
