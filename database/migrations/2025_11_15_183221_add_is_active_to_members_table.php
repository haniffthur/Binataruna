<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            // Tambahkan kolom 'is_active' setelah kolom 'name'
            // Kita set default(true) agar semua member lama Anda
            // otomatis dianggap "Aktif" dan tidak terpengaruh.
            $table->boolean('is_active')->default(true)->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};