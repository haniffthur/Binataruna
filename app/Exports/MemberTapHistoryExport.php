<?php

namespace App\Exports;

use App\Models\TapLog;
use App\Models\Member;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Carbon\Carbon;

class MemberTapHistoryExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithCustomStartCell, WithEvents, WithTitle
{
    protected $member;
    protected $filters;

    public function __construct(Member $member, $filters)
    {
        $this->member = $member;
        $this->filters = $filters;
    }

    /**
     * Nama Tab Sheet di Excel
     */
    public function title(): string
    {
        // Batasi nama sheet maksimal 30 karakter agar tidak error
        return substr($this->member->name, 0, 30);
    }

    /**
     * Mulai data tabel di baris ke-6
     */
    public function startCell(): string
    {
        return 'A6';
    }

    public function query()
    {
        $query = TapLog::query();

        // --- PERBAIKAN LOGIKA DI SINI ---
        // Kita ambil master_card_id langsung dari object member
        if ($this->member->master_card_id) {
            $query->where('master_card_id', $this->member->master_card_id);
        } else {
            // Jika member tidak punya kartu, pastikan query tidak mengembalikan hasil apa-apa
            $query->whereRaw('1 = 0');
        }
        // --------------------------------

        // Filter Tanggal
        if (!empty($this->filters['start_date']) && !empty($this->filters['end_date'])) {
            $query->whereBetween('tapped_at', [
                Carbon::parse($this->filters['start_date'])->startOfDay(),
                Carbon::parse($this->filters['end_date'])->endOfDay()
            ]);
        }

        // Filter Status
        if (isset($this->filters['status']) && $this->filters['status'] !== 'all') {
            $query->where('status', $this->filters['status']);
        }

        return $query->orderBy('tapped_at', 'desc');
    }

    public function headings(): array
    {
        return [
            'Waktu Tap',
            'Status',
            'Keterangan / Pesan'
        ];
    }

    public function map($log): array
    {
        return [
            Carbon::parse($log->tapped_at)->format('d-m-Y H:i:s'),
            $log->status == 1 ? 'BERHASIL' : 'GAGAL',
            $log->message
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [];
    }

    /**
     * Styling Professional
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;
                $highestRow = $sheet->getHighestRow();

                // --- 1. HEADER JUDUL ---
                $sheet->mergeCells('A1:C1');
                $sheet->setCellValue('A1', 'LAPORAN RIWAYAT TAPPING');
                
                $sheet->mergeCells('A2:C2');
                $sheet->setCellValue('A2', 'BINA TARUNA');

                // Info Member
                $sheet->setCellValue('A4', 'Nama Member: ' . $this->member->name);
                
                // Info Periode
                $startDate = !empty($this->filters['start_date']) ? Carbon::parse($this->filters['start_date'])->format('d/m/Y') : 'Awal';
                $endDate = !empty($this->filters['end_date']) ? Carbon::parse($this->filters['end_date'])->format('d/m/Y') : 'Sekarang';
                $sheet->setCellValue('C4', 'Periode: ' . $startDate . ' - ' . $endDate);

                // Styling Header Judul
                $sheet->getStyle('A1:A2')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
                $sheet->getStyle('A4:C4')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11],
                ]);
                $sheet->getStyle('C4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // --- 2. STYLING TABEL DATA ---
                $headerStyle = [
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '4e73df']
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ];
                $sheet->getStyle('A6:C6')->applyFromArray($headerStyle);
                $sheet->getRowDimension(6)->setRowHeight(25);

                if ($highestRow >= 7) {
                    // Border Tabel
                    $sheet->getStyle('A6:C' . $highestRow)->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => '000000'],
                            ],
                        ],
                    ]);

                    // Rata Tengah & Zebra Striping
                    for ($row = 7; $row <= $highestRow; $row++) {
                        $sheet->getStyle('A' . $row . ':B' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        
                        if ($row % 2 == 0) {
                            $sheet->getStyle('A' . $row . ':C' . $row)->getFill()
                                ->setFillType(Fill::FILL_SOLID)
                                ->getStartColor()->setARGB('F2F2F2');
                        }

                        $statusVal = $sheet->getCell('B' . $row)->getValue();
                        if ($statusVal == 'BERHASIL') {
                            $sheet->getStyle('B' . $row)->getFont()->getColor()->setARGB('1CC88A'); // Hijau
                            $sheet->getStyle('B' . $row)->getFont()->setBold(true);
                        } else {
                            $sheet->getStyle('B' . $row)->getFont()->getColor()->setARGB('E74A3B'); // Merah
                            $sheet->getStyle('B' . $row)->getFont()->setBold(true);
                        }
                    }
                }
            },
        ];
    }
}