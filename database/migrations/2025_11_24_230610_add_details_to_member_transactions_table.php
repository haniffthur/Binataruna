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
    Schema::table('member_transactions', function (Blueprint $table) {
        // Menambah kolom uang pendaftaran (default 0)
        $table->decimal('registration_fee', 15, 2)->default(0)->after('total_amount');

        // Menambah kolom catatan (opsional)
        $table->text('notes')->nullable()->after('change');
    });
}

public function down(): void
{
    Schema::table('member_transactions', function (Blueprint $table) {
        $table->dropColumn(['registration_fee', 'notes']);
    });
}
};
