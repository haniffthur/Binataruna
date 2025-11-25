<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MasterCard;
use App\Models\TapLog;
use Carbon\Carbon;

class TapValidationController extends Controller
{
    /**
     * Memvalidasi tap dari mesin RFID.
     * * Urutan Pengecekan:
     * 1. Kartu Terdaftar?
     * 2. Apakah ini Tap Duplikat (Debounce)?
     * 3. Pemilik Kartu Ditemukan?
     * 4. Apakah Akun Member Aktif (Tidak Cuti)?
     * 5. Validasi Aturan (Hari, Jam, Kuota)?
     * 6. Akses Diberikan.
     */
    public function validateTap(Request $request)
    {
        // Validasi input awal
        $validatedData = $request->validate(['cardno' => 'required|string']);
        $now = Carbon::now();
        $cardno = $validatedData['cardno'];

        // --- 1. Pengecekan Kartu Terdaftar ---
        $card = MasterCard::with('member.accessRule', 'coach.accessRule', 'staff.accessRule')
            ->where('cardno', $cardno)->first();

        if (!$card || $card->assignment_status == 'available') {
            TapLog::create([
                'master_card_id' => $card?->id, // Menggunakan null-safe operator jika $card null
                'card_uid' => $cardno,
                'status' => 0,
                'message' => 'Kartu tdk terdftr',
                'tapped_at' => $now
            ]);
            return response()->json(['Status' => 0, 'Message' => 'Krtu tdk terdftr',  'FullName' => 'Nama tidak terdaftar',  'Cardno' => $cardno, 'UTC' => $now->format('d-m-Y H:i:s')], 404);
        }

        // --- 2. Pengecekan Anti-Double Tap (Debounce) ---
        // Cek log terakhir (apapun statusnya) untuk kartu ini
        $lastTap = TapLog::where('master_card_id', $card->id)
                        ->latest('tapped_at')
                        ->first();
        
        $debounceSeconds = 3; // Blokir tap duplikat dalam 3 detik

        if ($lastTap && $lastTap->tapped_at->diffInSeconds($now) < $debounceSeconds) {
            // Ini adalah tap duplikat.
            // Jangan buat log baru, kirim saja respons yang SAMA dengan log terakhir
            // agar mesin tapping "puas" dan tidak mengirim request lagi.
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
                ], 403); 
            }
        }
        
        // --- 3. Pengecekan Pemilik Kartu ---
        // Kode di bawah ini HANYA akan berjalan jika ini BUKAN tap duplikat
        $owner = $card->member ?? $card->coach ?? $card->staff;

        if (!$owner) {
            TapLog::create(['master_card_id' => $card->id, 'card_uid' => $cardno, 'status' => 'denied', 'message' => 'Pemilik kartu tidak ditemukan.', 'tapped_at' => $now]);
            return response()->json(['Status' => 0, 'Message' => 'Pemilik kartu tidak ditemukan.', 'Cardno' => $cardno, 'UTC' => $now->format('d-m-Y H:i:s')], 404);
        }

        // --- 4. Pengecekan Status Aktif/Cuti (Hanya untuk Member) ---
        if ($card->member) { 
            // Jika 'is_active' bernilai false (0)
            if ($owner->is_active == false) {
                $message = 'Akses ditolak: Akun tdk aktif (Cuti).';
                TapLog::create([
                    'master_card_id' => $card->id,
                    'card_uid' => $cardno,
                    'status' => 0, // Gagal
                    'message' => $message,
                    'tapped_at' => $now
                ]);
                return response()->json([
                    'Status' => 0, 
                    'Message' => 'Krtu tdk aktif', 
                    'FullName' => $owner->name,
                    'Cardno' => $cardno, 
                    'UTC' => $now->format('d-m-Y H:i:s')
                ], 403); // 403 = Forbidden (Dilarang)
            }
        }

        // --- 5. Validasi Aturan (Hari, Jam, Kuota) ---
        $rule = null;
        if (isset($owner->rule_type) && $owner->rule_type == 'custom') {
            $rule = $owner;
        } else if ($owner->accessRule) {
            $rule = $owner->accessRule;
        }

        // Jika tidak ada aturan, langsung berikan akses
        if (!$rule) {
            TapLog::create(['master_card_id' => $card->id, 'card_uid' => $cardno, 'status' => 1, 'message' => 'Akses diberikan (tanpa aturan).', 'tapped_at' => $now]);
            return response()->json(['Status' => 1, 'Message' => 'Akses Diberikan', 'FullName' => $owner->name, 'Cardno' => $cardno, 'UTC' => $now->format('d-m-Y H:i:s')]);
        }

        // Validasi Hari
        $today = strtolower($now->format('l'));
        if ($rule->allowed_days && !in_array($today, $rule->allowed_days)) {
            $message = 'Akses ditolak: Bukan hari yang diizinkan.';
            TapLog::create(['master_card_id' => $card->id, 'card_uid' => $cardno, 'status' => 0, 'message' => $message, 'tapped_at' => $now]);
            return response()->json(['Status' => 0, 'Message' => $message, 'Cardno' => $cardno, 'UTC' => $now->format('d-m-Y H:i:s')], 403);
        }

        // Validasi Jam
        $currentTime = $now->format('H:i:s');
        $startTime = $rule->start_time ? Carbon::parse($rule->start_time)->format('H:i:s') : null;
        $endTime = $rule->end_time ? Carbon::parse($rule->end_time)->format('H:i:s') : null;

        if (($startTime && $currentTime < $startTime) || ($endTime && $currentTime > $endTime)) {
            $message = 'Akses ditolak: Di luar jam operasional.';
            TapLog::create(['master_card_id' => $card->id, 'card_uid' => $cardno, 'status' => 0, 'message' => $message, 'tapped_at' => $now]);
            return response()->json(['Status' => 0, 'Message' => $message, 'Cardno' => $cardno, 'UTC' => $now->format('d-m-Y H:i:s')], 403);
        }

        // Validasi Limit Harian
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

        // Validasi Limit Bulanan
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

        // --- 6. Akses Diberikan (Sukses) ---
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