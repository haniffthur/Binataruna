<?php

namespace App\Http\Controllers;

use App\Models\TapLog;
use App\Models\SchoolClass;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Exports\TapLogsExport;
use Maatwebsite\Excel\Facades\Excel;

class TaplogsController extends Controller
{
    /**
     * Menampilkan halaman riwayat tap kartu dengan filter.
     */
    public function index(Request $request)
    {
        $schoolClasses = SchoolClass::orderBy('name')->get();
        $query = $this->buildQuery($request);
        $logs = $query->latest('tapped_at')->paginate(25)->withQueryString(); 

        return view('taplogs.index', compact('logs', 'schoolClasses'));
    }

    /**
     * Mengambil log terbaru untuk pembaruan real-time (AJAX) dengan filter.
     */
    public function fetchLatest(Request $request)
    {
        $request->validate(['since_id' => 'nullable|integer']);
        $sinceId = $request->input('since_id', 0);
        
        // Gunakan method buildQuery yang sama untuk konsistensi data
        $query = $this->buildQuery($request);
        if ($sinceId == 0) {
            // LOGIKA BARU: Jika ID 0 (Halaman baru dibuka), ambil 1 terakhir saja
            // Ini agar muatnya cepat dan tidak memicu notifikasi untuk data lama
            $newLogs = $query->latest('tap_logs.id')->take(1)->get()->reverse(); 
            // reverse() dikembalikan agar urutan array tetap ID kecil ke besar
        } else {
            // Jika ID > 0 (Polling rutin), ambil yang lebih baru dari ID itu
            $newLogs = $query->where('tap_logs.id', '>', $sinceId)->get();
        }

        $newLogs = $query->where('tap_logs.id', '>', $sinceId)->get();

        // Ubah data menjadi format yang mudah digunakan oleh JavaScript
        $formattedLogs = $newLogs->map(function ($log) {
            
            // Logika ini mengambil data dari hasil JOIN di buildQuery
            $ownerName = $log->member_name ?? $log->coach_name ?? $log->staff_name ?? 'Kartu Tidak Terhubung';
            if(!$log->master_card_id) {
                $ownerName = 'Kartu Telah Dihapus';
            }

            $ownerType = 'Tidak Diketahui';
            $cardDetail = '-';
            if($log->member_name) { 
                $ownerType = 'Member'; 
                $cardDetail = $log->class_name ?? 'Tanpa Kelas'; 
            }
            elseif($log->coach_name) { 
                $ownerType = 'Pelatih'; 
                $cardDetail = $log->coach_specialization ?? 'Tanpa Spesialisasi'; 
            }
            elseif($log->staff_name) { 
                $ownerType = 'Staff'; 
                $cardDetail = $log->staff_position ?? 'Tanpa Posisi'; 
            }

            return [
                'id' => $log->id,
                'tapped_at' => Carbon::parse($log->tapped_at)->format('d M Y, H:i:s'),
                'card_uid' => $log->cardno ?? $log->card_uid, // Prioritaskan cardno
                'owner_name' => $ownerName,
                'owner_type' => $ownerType,
                'owner_detail' => $cardDetail,
                'status' => $log->status,
                'message' => $log->message,
            ];
        });
        return response()->json($formattedLogs);
    }

    /**
     * Menangani permintaan ekspor ke Excel.
     */
    public function exportExcel(Request $request)
    {
        $fileName = 'Laporan_Log_Tap_' . now()->format('Y-m-d_H-i') . '.xlsx';
        return Excel::download(new TapLogsExport($request), $fileName);
    }

    /**
     * Method private untuk membangun query yang kompleks agar tidak duplikasi kode.
     */
    private function buildQuery(Request $request)
    {
        $query = TapLog::query()
            ->select(
                'tap_logs.*', 
                'members.name as member_name', 
                'coaches.name as coach_name', 
                'staffs.name as staff_name',
                'classes.name as class_name',
                'coaches.specialization as coach_specialization',
                'staffs.position as staff_position',
                'master_cards.cardno'
            )
            ->leftJoin('master_cards', 'tap_logs.master_card_id', '=', 'master_cards.id')
            ->leftJoin('members', 'master_cards.id', '=', 'members.master_card_id')
            ->leftJoin('coaches', 'master_cards.id', '=', 'coaches.master_card_id')
            ->leftJoin('staffs', 'master_cards.id', '=', 'staffs.master_card_id')
            ->leftJoin('classes', 'members.school_class_id', '=', 'classes.id');

        if ($request->filled('name')) {
            $name = $request->name;
            $query->where(function ($q) use ($name) {
                $q->where('members.name', 'like', "%{$name}%")
                  ->orWhere('coaches.name', 'like', "%{$name}%")
                  ->orWhere('staffs.name', 'like', "%{$name}%");
            });
        }

        if ($request->filled('class_id')) {
            $query->where('members.school_class_id', $request->class_id);
        }

        if ($request->filled('owner_type')) {
            switch ($request->owner_type) {
                case 'member': $query->whereNotNull('members.id'); break;
                case 'coach': $query->whereNotNull('coaches.id'); break;
                case 'staff': $query->whereNotNull('staffs.id'); break;
            }
        }
        // --- PERBAIKAN: Tambahkan filter berdasarkan status ---
        if ($request->filled('status')) {
            if ($request->status == 'granted') {
                $query->where('tap_logs.status', 1);
            } elseif ($request->status == 'denied') {
                $query->where('tap_logs.status', 0);
            }
        }

        $period = $request->input('period', 'all_time');
        $start = null; $end = null;
        switch ($period) {
            case 'today': $start = now()->startOfDay(); $end = now()->endOfDay(); break;
            case 'this_week': $start = now()->startOfWeek(); $end = now()->endOfWeek(); break;
            case 'this_month': $start = now()->startOfMonth(); $end = now()->endOfMonth(); break;
            case 'custom':
                if ($request->filled('start_date') && $request->filled('end_date')) {
                    $start = Carbon::parse($request->start_date)->startOfDay();
                    $end = Carbon::parse($request->end_date)->endOfDay();
                }
                break;
        }
        if ($start && $end) {
            $query->whereBetween('tap_logs.tapped_at', [$start, $end]);
        }

        return $query;
    }
    
}