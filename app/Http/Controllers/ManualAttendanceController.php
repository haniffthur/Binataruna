<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Member;
use App\Models\MasterCard;
use App\Models\TapLog;
use Carbon\Carbon;

class ManualAttendanceController extends Controller
{
    /**
     * Menampilkan halaman absensi manual.
     */
    public function index()
    {
        return view('manual-attendance.index');
    }

    /**
     * Mencari member untuk dropdown autocomplete.
     */
    public function searchMembers(Request $request)
    {
        $search = $request->get('q');
        
        $members = Member::with('masterCard')
            ->where('name', 'like', "%{$search}%")
            ->whereHas('masterCard', function($query) {
                $query->where('assignment_status', 'assigned');
            })
            ->limit(10)
            ->get();

        $results = $members->map(function ($member) {
            return [
                'id'     => $member->id,
                'text'   => $member->name, // Dibuat lebih bersih, hanya nama
                'cardno' => $member->masterCard->cardno ?? ''
            ];
        });

        return response()->json(['results' => $results]);
    }

    /**
     * Memproses absensi manual yang disubmit.
     */
    public function processAttendance(Request $request)
    {
        $request->validate([
            'member_id' => 'required|exists:members,id',
            'cardno'    => 'required|string'
        ]);

        $member = Member::with('masterCard', 'accessRule')->find($request->member_id);
        
        // Validasi keamanan: pastikan member dan kartu cocok
        if (!$member || !$member->masterCard || $member->masterCard->cardno !== $request->cardno) {
            return response()->json([
                'success' => false,
                'message' => 'Data member atau kartu tidak valid.'
            ], 400);
        }

        $now = Carbon::now();
        $card = $member->masterCard;
        $message = 'Absen manual berhasil';
        $status = 1;

        // Tentukan aturan akses yang berlaku
        $rule = null;
        if (isset($member->rule_type) && $member->rule_type == 'custom') {
            $rule = $member;
        } else if ($member->accessRule) {
            $rule = $member->accessRule;
        }

        // Jalankan validasi aturan jika ada
        if ($rule) {
            $today = strtolower($now->format('l'));
            $currentTime = $now->format('H:i:s');
            
            // Cek batasan hari
            if ($rule->allowed_days && !in_array($today, $rule->allowed_days)) {
                $message = 'Ditolak: Bukan hari yang diizinkan';
                $status = 0;
            }
            // Cek batasan waktu
            else {
                $startTime = $rule->start_time ? Carbon::parse($rule->start_time)->format('H:i:s') : null;
                $endTime = $rule->end_time ? Carbon::parse($rule->end_time)->format('H:i:s') : null;
                if (($startTime && $currentTime < $startTime) || ($endTime && $currentTime > $endTime)) {
                    $message = 'Ditolak: Di luar jam operasional';
                    $status = 0;
                }
            }

            // Cek limit harian (hanya jika status masih diizinkan)
            if ($status === 1 && $rule->max_taps_per_day !== null && $rule->max_taps_per_day >= 0) {
                $tapCount = TapLog::where('master_card_id', $card->id)
                    ->whereDate('tapped_at', $now->toDateString())
                    ->where('status', 1)->count();
                if ($tapCount >= $rule->max_taps_per_day) {
                    $message = 'Ditolak: Limit harian habis';
                    $status = 0;
                }
            }
        }

        // Buat log absensi
        TapLog::create([
            'master_card_id' => $card->id,
            'card_uid'       => $request->cardno,
            'status'         => $status,
            'message'        => $message . ' (Manual)',
            'tapped_at'      => $now
        ]);

        return response()->json([
            'success'     => $status == 1,
            'message'     => $message,
            'member_name' => $member->name,
            'time'        => $now->format('d M Y, H:i:s')
        ]);
    }

    /**
     * Mengambil data absensi manual terbaru hari ini.
     */
    public function recent()
    {
        $recentLogs = TapLog::with('masterCard.member')
            ->whereDate('tapped_at', Carbon::today())
            ->where('message', 'like', '%(Manual)%')
            ->orderBy('tapped_at', 'desc')
            ->limit(10)
            ->get();
        
        $formattedLogs = $recentLogs->map(function($log) {
            return [
                'member_name' => $log->masterCard->member->name ?? 'Unknown',
                'status'      => $log->status,
                'message'     => str_replace(' (Manual)', '', $log->message),
                'time'        => Carbon::parse($log->tapped_at)->format('H:i:s')
            ];
        });
        return response()->json($formattedLogs);
    }
}