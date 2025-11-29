<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Member;
use App\Models\MemberTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class BlockUnpaidMembers extends Command
{
    /**
     * Nama perintah yang akan diketik di terminal untuk menjalankan robot.
     */
    protected $signature = 'members:block-unpaid';

    /**
     * Penjelasan singkat perintah.
     */
    protected $description = 'Memblokir member yang belum bayar bulanan setelah tanggal 10.';

    /**
     * Eksekusi Logika Robot.
     */
    public function handle()
    {
        $today = Carbon::now();
        
        // 1. Safety Check: Fitur ini HANYA boleh jalan jika tanggal >= 10
        // (Jika hari ini tanggal 1-9, robot istirahat/tidak melakukan apa-apa)
        if ($today->day < 10) {
            $this->info("Hari ini tanggal {$today->day }. Belum masuk tanggal blokir (Tgl 10).");
            return;
        }

        $this->info("Memulai pengecekan pembayaran member untuk bulan: " . $today->format('F Y'));
        Log::info("SCHEDULER: Memulai pengecekan blokir otomatis.");

        // 2. Ambil semua member yang masih AKTIF (is_active = 1)
        // Kita tidak perlu mengecek member yang memang sudah cuti
        $activeMembers = Member::where('is_active', true)->get();
        
        $blockedCount = 0;
        
        // Tampilkan progress bar di terminal (biar keren & jelas progresnya)
        $bar = $this->output->createProgressBar(count($activeMembers));
        $bar->start();

        foreach ($activeMembers as $member) {
            // 3. Cek Transaksi: Apakah member ini punya transaksi di BULAN INI & TAHUN INI?
            $hasPaidThisMonth = MemberTransaction::where('member_id', $member->id)
                ->whereYear('transaction_date', $today->year)
                ->whereMonth('transaction_date', $today->month)
                ->exists();

            // 4. Jika BELUM BAYAR -> Blokir!
            if (!$hasPaidThisMonth) {
                $member->update(['is_active' => false]);
                
                // Catat log (penting untuk audit jika ada komplain)
                Log::warning("BLOKIR OTOMATIS: Member {$member->name} (ID: {$member->id}) dinonaktifkan karena belum bayar.");
                
                $blockedCount++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        
        $this->info("Selesai! Total member diblokir hari ini: {$blockedCount}");
        Log::info("SCHEDULER SELESAI. Total diblokir: {$blockedCount}");
    }
}