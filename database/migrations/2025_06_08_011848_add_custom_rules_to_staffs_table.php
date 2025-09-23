<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up(): void {
        Schema::table('staffs', function (Blueprint $table) {
            $table->tinyInteger('max_taps_per_day')->unsigned()->nullable()->after('access_rule_id');
            $table->integer('max_taps_per_month')->unsigned()->nullable()->after('max_taps_per_day');
            $table->json('allowed_days')->nullable()->after('max_taps_per_month');
            $table->time('start_time')->nullable()->after('allowed_days');
            $table->time('end_time')->nullable()->after('start_time');
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staffs', function (Blueprint $table) {
            //
        });
    }
};
