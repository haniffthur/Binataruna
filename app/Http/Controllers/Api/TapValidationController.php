<?php
// =====================================
// FILE 2: TAP VALIDATION CONTROLLER
// Path: app/Http/Controllers/Api/TapValidationController.php
// =====================================

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MasterCard;
use App\Models\TapLog;
use Carbon\Carbon;

class TapValidationController extends Controller
{
    public function validateTap(Request $request)
    {
        $validatedData = $request->validate(['cardno' => 'required|string']);
        $now = Carbon::now();
        $cardno = $validatedData['cardno'];

        $card = MasterCard::with('member.accessRule', 'coach.accessRule', 'staff.accessRule')
            ->where('cardno', $cardno)->first();

        // --- Blok 1: Kartu Tidak Ditemukan ---
        if (!$card || $card->assignment_status == 'available') {
            TapLog::create([
                // === PERBAIKAN BUG ===
                // Menggunakan null-safe operator (?->) untuk mencegah error
                // jika $card null tapi kita mencoba mengambil $card->id
                'master_card_id' => $card?->id, 
                'card_uid' => $cardno, // Simpan UID yang di-scan
                'status' => 0,
                'message' => 'Kartu tdk terdftr',
                'tapped_at' => $now
            ]);
            return response()->json(['Status' => 0, 'Message' => 'Krtu tdk terdftr',  'FullName' => 'Nama tidak terdaftar',  'Cardno' => $cardno, 'UTC' => $now->format('d-m-Y H:i:s')], 404);
        }

        // ================================================
        // === LOGIKA ANTI-DOUBLE TAP (DEBOUNCE) DIMULAI ===
        // ================================================
        // Cek log terakhir (apapun statusnya) untuk kartu ini
        $lastTap = TapLog::where('master_card_id', $card->id)
                        ->latest('tapped_at')
                        ->first();
        
        // Setel durasi debounce (3 detik)
        $debounceSeconds = 3; 

        if ($lastTap && $lastTap->tapped_at->diffInSeconds($now) < $debounceSeconds) {
            // Ini adalah tap duplikat.
            // Jangan buat log baru, kirim saja respons yang SAMA dengan log terakhir
            // agar mesin tapping "puas" dan tidak mengirim request lagi.
            
            // Ambil nama pemilik untuk respons
            $ownerName = $card->member?->name ?? $card->coach?->name ?? $card->staff?->name ?? 'Pengguna';

            if ($lastTap->status == 1) { 
                // Jika tap terakhir BERHASIL, kirim ulang respons BERHASIL
                return response()->json([
                    'Status' => 1, 
                    'Message' => 'Akses Diberikan', 
                    'FullName' => $ownerName, 
                    'Cardno' => $cardno,
                    'UTC' => $now->format('d-m-Y H:i:s')
                ], 200);
            } else {
                // Jika tap terakhir GAGAL, kirim ulang respons GAGAL
                return response()->json([
                    'Status' => 0, 
                    'Message' => $lastTap->message, // Gunakan pesan error dari log terakhir
                    'FullName' => $ownerName,
                    'Cardno' => $cardno, 
                    'UTC' => $now->format('d-m-Y H:i:s')
                ], 403); // 403 (Forbidden) atau 429 (Too Many Req)
            }
        }
        // ================================================
        // ===       LOGIKA DEBOUNCE SELESAI          ===
        // ================================================

        // --- Blok 2: Pemilik Kartu Tidak Ditemukan ---
        // Kode di bawah ini HANYA akan berjalan jika ini BUKAN tap duplikat
        $owner = $card->member ?? $card->coach ?? $card->staff;

        if (!$owner) {
            TapLog::create(['master_card_id' => $card->id, 'card_uid' => $cardno, 'status' => 'denied', 'message' => 'Pemilik kartu tidak ditemukan.', 'tapped_at' => $now]);
            return response()->json(['Status' => 0, 'Message' => 'Pemilik kartu tidak ditemukan.', 'Cardno' => $cardno, 'UTC' => $now->format('d-m-Y H:i:s')], 404);
        }

        $rule = null;
        if (isset($owner->rule_type) && $owner->rule_type == 'custom') {
            $rule = $owner;
        } else if ($owner->accessRule) {
            $rule = $owner->accessRule;
        }

        if (!$rule) {
            TapLog::create(['master_card_id' => $card->id, 'card_uid' => $cardno, 'status' => 1, 'message' => 'Akses diberikan (tanpa aturan).', 'tapped_at' => $now]);
            return response()->json(['Status' => 1, 'Message' => 'Akses Diberikan', 'FullName' => $owner->name, 'Cardno' => $cardno, 'UTC' => $now->format('d-m-Y H:i:s')]);
        }

        // --- Blok 3: Validasi Aturan (Hari, Jam, Kuota) ---
        $today = strtolower($now->format('l'));
        $currentTime = $now->format('H:i:s');
        $startTime = $rule->start_time ? Carbon::parse($rule->start_time)->format('H:i:s') : null;
        $endTime = $rule->end_time ? Carbon::parse($rule->end_time)->format('H:i:s') : null;

        if ($rule->allowed_days && !in_array($today, $rule->allowed_days)) {
            $message = 'Akses ditolak: Bukan hari yang diizinkan.';
            TapLog::create(['master_card_id' => $card->id, 'card_uid' => $cardno, 'status' => 0, 'message' => $message, 'tapped_at' => $now]);
            return response()->json(['Status' => 0, 'Message' => $message, 'Cardno' => $cardno, 'UTC' => $now->format('d-m-Y H:i:s')], 403);
        }

        if (($startTime && $currentTime < $startTime) || ($endTime && $currentTime > $endTime)) {
            $message = 'Akses ditolak: Di luar jam operasional.';
            TapLog::create(['master_card_id' => $card->id, 'card_uid' => $cardno, 'status' => 0, 'message' => $message, 'tapped_at' => $now]);
            return response()->json(['Status' => 0, 'Message' => $message, 'Cardno' => $cardno, 'UTC' => $now->format('d-m-Y H:i:s')], 403);
        }

        if ($rule->max_taps_per_day !== null && $rule->max_taps_per_day >= 0) {
            $dailyQuery = TapLog::where('master_card_id', $card->id)->whereDate('tapped_at', $now->toDateString())->where('status', 1);
            if ($owner->daily_tap_reset_at) {
                $dailyQuery->where('tapped_at', '>=', $owner->daily_tap_reset_at);
            }
            if ($dailyQuery->count() >= $rule->max_taps_per_day) {
                $message = 'Limit harian hbs';
                TapLog::create(['master_card_id' => $card->id, 'card_uid' => $cardno, 'status' => 0, 'message' => $message, 'tapped_at' => $now]);
                return response()->json(['Status' => 0, 'Message' => $message,  'FullName' => $owner->name,'Cardno' => $cardno, 'UTC' => $now->format('d-m-Y H:i:s')], 429);
            }
        }

        if ($rule->max_taps_per_month !== null && $rule->max_taps_per_month >= 0) {
            $monthlyQuery = TapLog::where('master_card_id', $card->id)->whereMonth('tapped_at', $now->month)->whereYear('tapped_at', $now->year)->where('status', 1);
            if ($owner->monthly_tap_reset_at) {
                $monthlyQuery->where('tapped_at', '>=', $owner->monthly_tap_reset_at);
            }
            if ($monthlyQuery->count() >= $rule->max_taps_per_month) {
                $message = 'Limit bulnan hbs';
                TapLog::create(['master_card_id' => $card->id, 'card_uid' => $cardno, 'status' => 0, 'message' => $message, 'tapped_at' => $now]);
                return response()->json(['Status' => 0, 'Message' => $message,  'FullName' => $owner->name, 'Cardno' => $cardno, 'UTC' => $now->format('d-m-Y H:i:s')], 429);
            }
        }

        // --- Blok 4: Akses Diberikan (Sukses) ---
        TapLog::create(['master_card_id' => $card->id, 'card_uid' => $cardno, 'status' => 1, 'message' => 'Akses diberikan.', 'tapped_at' => $now]);
        return response()->json([
            'Status' => 1, 
            'Message' => 'Akses Diberikan',
            'FullName' => $owner->name,   
            'Cardno' => $cardno,
            'UTC' => $now->format('d-m-Y H:i:s')
        ]);
    }
}