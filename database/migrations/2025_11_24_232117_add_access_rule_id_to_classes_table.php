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
    Schema::table('classes', function (Blueprint $table) {
        // Relasi ke tabel access_rules
        $table->foreignId('access_rule_id')
              ->nullable()
              ->constrained('access_rules')
              ->nullOnDelete(); 
    });
}

public function down(): void
{
    Schema::table('classes', function (Blueprint $table) {
        $table->dropForeign(['access_rule_id']);
        $table->dropColumn('access_rule_id');
    });
}
};
