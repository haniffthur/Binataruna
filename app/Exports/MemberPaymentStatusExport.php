<?php

namespace App\Exports;

use App\Models\Member;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
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

class MemberPaymentStatusExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithCustomStartCell, WithEvents
{
    protected $filters;
    protected $start;
    protected $end;

    public function __construct(array $filters)
    {
        $this->filters = $filters;

        // Tentukan Rentang Tanggal
        $this->start = null;
        $this->end = null;

        if (isset($filters['period'])) {
            switch ($filters['period']) {
                case 'today': $this->start = now()->startOfDay(); $this->end = now()->endOfDay(); break;
                case 'this_week': $this->start = now()->startOfWeek(); $this->end = now()->endOfWeek(); break;
                case 'this_month': $this->start = now()->startOfMonth(); $this->end = now()->endOfMonth(); break;
                case 'custom':
                    if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
                        $this->start = Carbon::parse($filters['start_date'])->startOfDay();
                        $this->end = Carbon::parse($filters['end_date'])->endOfDay();
                    }
                    break;
            }
        }
        
        // Default jika tidak ada filter: Bulan Ini
        if (!$this->start) {
            $this->start = now()->startOfMonth();
            $this->end = now()->endOfMonth();
        }
    }

    public function startCell(): string
    {
        return 'A5'; 
    }

    public function collection()
    {
        // 1. Ambil SEMUA Member (Aktif maupun Nonaktif/Cuti)
        $query = Member::with(['schoolClass', 'masterCard']);

        // 2. Filter Nama & Kelas (Jika ada input filter)
        if (!empty($this->filters['name'])) {
            $query->where('name', 'like', '%' . $this->filters['name'] . '%');
        }
        if (!empty($this->filters['class_id'])) {
            $query->where('school_class_id', $this->filters['class_id']);
        }

        // 3. Eager Load Transaksi di Rentang Waktu Tersebut
        $query->with(['transactions' => function($q) {
            if ($this->start && $this->end) {
                $q->whereBetween('transaction_date', [$this->start, $this->end]);
            }
            $q->latest('transaction_date');
        }]);

        // === KUNCI PERUBAHAN: URUTKAN BERDASARKAN NAMA (A-Z) ===
        // Ini akan mencampur yang sudah bayar dan belum, murni urut abjad
        return $query->orderBy('name', 'asc')->get();
    }

    public function headings(): array
    {
        return ['ID Member', 'Nama Member', 'Status Member', 'Kelas', 'Status Pembayaran', 'Tanggal Bayar', 'Total Bayar'];
    }

    public function map($member): array
    {
        // Ambil transaksi pertama (terbaru) dalam periode tersebut
        $transaction = $member->transactions->first(); 
        
        // Cek Status Pembayaran
        $paymentStatus = $transaction ? 'LUNAS' : 'BELUM BAYAR';
        
        // Cek Status Member (Aktif/Cuti)
        $memberStatus = $member->is_active ? 'AKTIF' : 'CUTI (NONAKTIF)';

        $date = $transaction ? Carbon::parse($transaction->transaction_date)->format('d-m-Y H:i') : '-';
        $amount = $transaction ? $transaction->total_amount : '-';

        return [
            $member->nis ?? $member->id,
            $member->name,
            $memberStatus,
            $member->schoolClass->name ?? '-',
            $paymentStatus,
            $date,
            $amount
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;
                $highestRow = $sheet->getHighestRow();
                $lastCol = 'G'; // Kolom sampai G

                // JUDUL LAPORAN
                $sheet->mergeCells('A1:'.$lastCol.'1');
                $sheet->setCellValue('A1', 'LAPORAN STATUS PEMBAYARAN MEMBER');
                $sheet->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 16], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);

                // INFO PERIODE
                $sheet->mergeCells('A2:'.$lastCol.'2');
                $periodeInfo = ($this->start && $this->end) 
                    ? $this->start->format('d M Y') . ' s/d ' . $this->end->format('d M Y') 
                    : 'Semua Waktu';
                $sheet->setCellValue('A2', 'Periode: ' . $periodeInfo);
                $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // HEADER TABEL (BIRU)
                $sheet->getStyle('A5:'.$lastCol.'5')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4e73df']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
                ]);
                $sheet->getRowDimension(5)->setRowHeight(25);

                // DATA & STYLING LOGIC
                if ($highestRow >= 6) {
                    $sheet->getStyle('A5:'.$lastCol.$highestRow)->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]]);
                    
                    // Format Rupiah (Kolom G)
                    $sheet->getStyle('G6:G'.$highestRow)->getNumberFormat()->setFormatCode('#,##0');

                    for ($row = 6; $row <= $highestRow; $row++) {
                        $memberStatus = $sheet->getCell('C'.$row)->getValue(); // Kolom Status Member
                        $paymentStatus = $sheet->getCell('E'.$row)->getValue(); // Kolom Status Bayar
                        
                        // Align Center untuk beberapa kolom
                        $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); 
                        $sheet->getStyle('C'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); 
                        $sheet->getStyle('D'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); 
                        $sheet->getStyle('E'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); 
                        $sheet->getStyle('F'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); 

                        // 1. JIKA STATUS "CUTI", WARNAI BARIS JADI KUNING
                        if (str_contains($memberStatus, 'CUTI')) {
                            $sheet->getStyle('A'.$row.':'.$lastCol.$row)->getFill()
                                ->setFillType(Fill::FILL_SOLID)
                                ->getStartColor()->setARGB('FFFACD'); // Kuning Muda
                        }

                        // 2. WARNA TEKS STATUS PEMBAYARAN
                        if ($paymentStatus == 'BELUM BAYAR') {
                            $sheet->getStyle('E'.$row)->getFont()->getColor()->setARGB('E74A3B'); // Merah
                            $sheet->getStyle('E'.$row)->getFont()->setBold(true);
                        } else {
                            $sheet->getStyle('E'.$row)->getFont()->getColor()->setARGB('1CC88A'); // Hijau
                            $sheet->getStyle('E'.$row)->getFont()->setBold(true);
                        }
                    }
                }
            },
        ];
    }
}