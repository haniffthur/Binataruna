<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Carbon\Carbon;

class TransactionsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithCustomStartCell, WithEvents
{
    protected $filters;

    public function __construct(array $filters)
    {
        $this->filters = $filters;
    }

    public function startCell(): string
    {
        return 'A5'; 
    }

    public function query()
    {
        $filters = $this->filters;
        $start = null;
        $end = null;

        if (isset($filters['period'])) {
            switch ($filters['period']) {
                case 'today': $start = now()->startOfDay(); $end = now()->endOfDay(); break;
                case 'this_week': $start = now()->startOfWeek(); $end = now()->endOfWeek(); break;
                case 'this_month': $start = now()->startOfMonth(); $end = now()->endOfMonth(); break;
                case 'custom':
                    if (isset($filters['start_date']) && isset($filters['end_date'])) {
                        $start = Carbon::parse($filters['start_date'])->startOfDay();
                        $end = Carbon::parse($filters['end_date'])->endOfDay();
                    }
                    break;
            }
        }

        // 1. Query Member (Tambahkan 'notes')
        $memberQuery = DB::table('member_transactions')
            ->join('members', 'member_transactions.member_id', '=', 'members.id')
            ->leftJoin('transaction_details', 'member_transactions.id', '=', 'transaction_details.detailable_id')
            ->leftJoin('classes', 'transaction_details.purchasable_id', '=', 'classes.id')
            ->where('transaction_details.detailable_type', 'App\\Models\\MemberTransaction')
            ->select(
                'member_transactions.id', 
                'members.name as customer_name', 
                'member_transactions.total_amount', 
                'member_transactions.transaction_date',
                'member_transactions.notes', // <--- TAMBAHKAN INI
                DB::raw("'Member' as transaction_type"), 
                'classes.name as item_name', 
                'classes.id as class_id'
            );

        // 2. Query Non-Member (Tambahkan 'notes' dummy/kosong)
        $nonMemberQuery = DB::table('non_member_transactions')
            ->select(
                'id', 
                'customer_name', 
                'total_amount', 
                'transaction_date', 
                DB::raw("'-' as notes"), // <--- Non member tidak punya notes (atau sesuaikan jika ada)
                DB::raw("'Non-Member' as transaction_type"), 
                DB::raw("'(Tiket Non-Member)' as item_name"), 
                DB::raw("NULL as class_id")
            );

        if (!empty($filters['name'])) {
            $memberQuery->where('members.name', 'like', '%' . $filters['name'] . '%');
            $nonMemberQuery->where('non_member_transactions.customer_name', 'like', '%' . $filters['name'] . '%');
        }
        if ($start && $end) {
            $memberQuery->whereBetween('member_transactions.transaction_date', [$start, $end]);
            $nonMemberQuery->whereBetween('non_member_transactions.transaction_date', [$start, $end]);
        }
        if (!empty($filters['class_id'])) {
            $memberQuery->where('classes.id', $filters['class_id']);
            $nonMemberQuery->whereRaw('1 = 0'); 
        }

        $allTransactionsUnion = $memberQuery->unionAll($nonMemberQuery);
        $finalQuery = DB::query()->fromSub($allTransactionsUnion, 'transactions');

        if (!empty($filters['type']) && $filters['type'] !== 'all') {
            $finalQuery->where('transaction_type', ucfirst($filters['type']));
        }

        return $finalQuery->orderBy('transaction_date', 'desc');
    }

    public function headings(): array
    {
        // Tambahkan Header Keterangan
        return ['ID Transaksi', 'Nama Pelanggan', 'Tipe', 'Item Dibeli', 'Total Bayar', 'Tanggal Transaksi', 'Keterangan'];
    }

    public function map($transaction): array
    {
        return [
            '#' . $transaction->id,
            $transaction->customer_name ?? 'Tamu',
            $transaction->transaction_type,
            $transaction->item_name ?? '-',
            $transaction->total_amount, 
            Carbon::parse($transaction->transaction_date)->format('d-m-Y H:i'),
            $transaction->notes ?? '-', // <--- Map Notes
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;
                $highestRow = $sheet->getHighestRow();
                $lastCol = 'G'; // Kolom sampai G (karena tambah Keterangan)

                // HEADER JUDUL
                $sheet->mergeCells('A1:' . $lastCol . '1');
                $sheet->setCellValue('A1', 'LAPORAN RIWAYAT TRANSAKSI');
                $sheet->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 16], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);

                // INFO PERIODE
                $sheet->mergeCells('A2:' . $lastCol . '2');
                $periodeText = 'Semua Waktu';
                if (isset($this->filters['period']) && $this->filters['period'] != 'all_time') {
                    $periodeText = ucfirst(str_replace('_', ' ', $this->filters['period']));
                }
                $sheet->setCellValue('A2', 'Periode: ' . $periodeText . ' | Export Date: ' . now()->format('d M Y H:i'));
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // STYLE HEADER TABEL (Baris 5)
                $sheet->getStyle('A5:' . $lastCol . '5')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4e73df']], 
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension(5)->setRowHeight(25);

                // BORDER & FORMAT DATA
                if ($highestRow >= 6) {
                    $sheet->getStyle('A5:' . $lastCol . $highestRow)->applyFromArray([
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
                    ]);
                    
                    // Format Rupiah (Kolom E)
                    $sheet->getStyle('E6:E' . $highestRow)->getNumberFormat()->setFormatCode('#,##0');
                    
                    // Rata Tengah
                    $sheet->getStyle('A6:A' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle('C6:C' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle('F6:F' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
            },
        ];
    }
}