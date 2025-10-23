<?php

namespace App\Exports;

use App\Models\Member;
use App\Models\TapLog;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AttendanceReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithTitle, WithEvents
{
    protected $filters;
    protected $startDate;
    protected $endDate;
    protected $tapTimeCategory; // <-- PROPERTI BARU DITAMBAHKAN
    protected $memberCounter = 0;
    protected $totalMembers = 0;
    protected $totalAttendance = 0;
    protected $activeMembers = 0;
    protected $inactiveMembers = 0;
    protected $noAttendanceMembers = 0;   // Member tanpa kehadiran (0)
    protected $membersWithCard = 0;       // Member yang sudah punya kartu RFID
    protected $membersWithoutCard = 0;    // Member yang belum punya kartu RFID

    public function __construct(array $filters)
    {
        $this->filters = $filters;
        
        // Set periode default jika tidak ada filter tanggal
        $this->startDate = isset($filters['start_date']) ? Carbon::parse($filters['start_date']) : Carbon::now()->startOfWeek();
        $this->endDate = isset($filters['end_date']) ? Carbon::parse($filters['end_date']) : Carbon::now()->endOfWeek();
        
        // === TAMBAHKAN INI ===
        // Simpan filter waktu tap
        $this->tapTimeCategory = $filters['tap_time_category'] ?? null;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $members = Member::with(['schoolClass', 'masterCard'])
            ->select(
                'id',
                'name',
                'school_class_id',
                'master_card_id'
            );

        // Terapkan filter yang diterima dari controller
        if (!empty($this->filters['member_id'])) {
            $members->where('id', $this->filters['member_id']); 
        }

        if (!empty($this->filters['school_class_id'])) {
            $members->where('school_class_id', $this->filters['school_class_id']);
        }

        if (!empty($this->filters['join_date'])) {
            $members->whereDate('join_date', $this->filters['join_date']);
        }

        // Urutkan berdasarkan kelas terlebih dahulu, kemudian nama member
        $result = $members->get()->sortBy([
            ['schoolClass.name', 'asc'],
            ['name', 'asc']
        ]);

        // Hitung attendance untuk setiap member
        $membersWithAttendance = $result->map(function ($member) {
            $attendanceCount = 0;
            
            if ($member->masterCard && $member->masterCard->id) {
                $this->membersWithCard++;

                // === LOGIKA QUERY DIUBAH UNTUK MENERAPKAN FILTER WAKTU ===
                
                // 1. Buat query dasar
                $tapQuery = TapLog::where('master_card_id', $member->masterCard->id)
                    ->where('status', 1) // Status granted
                    ->whereBetween('tapped_at', [
                        $this->startDate->startOfDay(),
                        $this->endDate->endOfDay()
                    ]);

                // 2. Terapkan filter waktu tap JIKA ada
                if ($this->tapTimeCategory) {
                    if ($this->tapTimeCategory == 'pagi') {
                        $tapQuery->whereTime('tapped_at', '>=', '01:00:00')
                                 ->whereTime('tapped_at', '<=', '11:59:59');
                    } elseif ($this->tapTimeCategory == 'siang') {
                        $tapQuery->whereTime('tapped_at', '>=', '12:00:00')
                                 ->whereTime('tapped_at', '<=', '14:59:59');
                    } elseif ($this->tapTimeCategory == 'sore') {
                        $tapQuery->whereTime('tapped_at', '>=', '15:00:00')
                                 ->whereTime('tapped_at', '<=', '18:00:00');
                    }
                }

                // 3. Hitung hasilnya
                $attendanceCount = $tapQuery->count();
                // === AKHIR PERUBAHAN LOGIKA QUERY ===

            } else {
                $this->membersWithoutCard++;
            }
            
            // Tambahkan attendance count ke member object
            $member->attendance_count = $attendanceCount;
            
            // Hitung total untuk statistik
            $this->totalAttendance += $attendanceCount;
            
            // Kategorikan member berdasarkan kehadiran
            if ($attendanceCount > 0) {
                $this->activeMembers++;
            } else {
                $this->inactiveMembers++;
                $this->noAttendanceMembers++;
            }
            
            return $member;
        });

        // Reset counter dan hitung total member
        $this->memberCounter = 0;
        $this->totalMembers = $membersWithAttendance->count();

        return $membersWithAttendance;
    }

    /**
     * Header untuk Excel
     */
    public function headings(): array
    {
        return [
            'No.',
            'Nama Member',
            'Kelas',
            'Kartu RFID',
            'Jumlah Kehadiran'
        ];
    }

    /**
     * Mapping data untuk setiap baris
     */
    public function map($member): array
    {
        $this->memberCounter++;
        
        return [
            $this->memberCounter,
            strtoupper($member->name),
            $member->schoolClass->name ?? 'Belum Ditentukan',
            $member->masterCard->cardno ?? 'Belum Terdaftar',
            $member->attendance_count ?? 0
        ];
    }

    /**
     * Styling untuk worksheet
     */
    public function styles(Worksheet $sheet)
    {
        return [
            // Style untuk header (baris 1)
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                    'color' => ['rgb' => 'FFFFFF']
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '2E8B57'] // Hijau
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000']
                    ]
                ]
            ],
            // Style untuk kolom No (kolom A)
            'A:A' => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'font' => ['size' => 10]
            ],
            // Style untuk kolom Nama (kolom B)
            'B:B' => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'font' => ['size' => 10]
            ],
            // Style untuk kolom Kelas (kolom C)
            'C:C' => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'font' => ['size' => 10]
            ],
            // Style untuk kolom RFID (kolom D)
            'D:D' => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'font' => [
                    'size' => 10,
                    'name' => 'Consolas'
                ]
            ],
            // Style untuk kolom Kehadiran (kolom E)
            'E:E' => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'font' => [
                    'size' => 11,
                    'bold' => true,
                    'color' => ['rgb' => '2E8B57']
                ]
            ]
        ];
    }

    /**
     * Lebar kolom
     */
    public function columnWidths(): array
    {
        return [
            'A' => 8,   // No.
            'B' => 25,  // Nama Member
            'C' => 15,  // Kelas
            'D' => 20,  // Kartu RFID
            'E' => 18,  // Jumlah Kehadiran
        ];
    }

    /**
     * Judul sheet
     */
    public function title(): string
    {
        return 'Laporan Absensi';
    }

    /**
     * Event untuk styling tambahan setelah sheet dibuat
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                
                // Border untuk seluruh data
                $sheet->getStyle('A1:E' . $highestRow)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000']
                        ]
                    ]
                ]);

                // Zebra striping
                for ($i = 2; $i <= $highestRow; $i++) {
                    if ($i % 2 == 0) {
                        $sheet->getStyle('A' . $i . ':E' . $i)->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'F2F2F2']
                            ]
                        ]);
                    }
                }

                // Conditional formatting untuk kolom attendance (E)
                for ($i = 2; $i <= $highestRow; $i++) {
                    $attendanceValue = $sheet->getCell('E' . $i)->getValue();
                    
                    if ($attendanceValue == 0) {
                        // Merah untuk yang tidak hadir
                        $sheet->getStyle('E' . $i)->applyFromArray([
                            'font' => [
                                'color' => ['rgb' => 'DC143C'],
                                'bold' => true
                            ]
                        ]);
                    }
                }

                // Freeze panes (membekukan baris header)
                $sheet->freezePane('A2');

                // Auto filter untuk header
                $sheet->setAutoFilter('A1:E' . $highestRow);

                // Menambahkan informasi di bagian bawah
                $footerRow = $highestRow + 2;
                $periodText = $this->startDate->format('d/m/Y') . ' - ' . $this->endDate->format('d/m/Y');
                
                // Informasi Periode dan Total
                $sheet->setCellValue('A' . $footerRow, 'STATISTIK LAPORAN KEHADIRAN');
                $sheet->setCellValue('A' . ($footerRow + 1), 'Periode: ' . $periodText);
                
                // === TAMBAHKAN INFO FILTER WAKTU TAP ===
                $timeFilterText = 'Semua Waktu';
                if ($this->tapTimeCategory == 'pagi') {
                    $timeFilterText = 'Pagi (01:00 - 11:59)';
                } elseif ($this->tapTimeCategory == 'siang') {
                    $timeFilterText = 'Siang (12:00 - 14:59)';
                } elseif ($this->tapTimeCategory == 'sore') {
                    $timeFilterText = 'Sore (15:00 - 18:00)';
                }
                $sheet->setCellValue('A' . ($footerRow + 2), 'Filter Waktu: ' . $timeFilterText);
                // === AKHIR PENAMBAHAN INFO ===

                
                // Total Member
                $sheet->setCellValue('A' . ($footerRow + 4), 'TOTAL MEMBER:');
                $sheet->setCellValue('A' . ($footerRow + 5), '• Total Member: ' . $this->totalMembers . ' orang');
                $sheet->setCellValue('A' . ($footerRow + 6), '• Member dengan Kartu RFID: ' . $this->membersWithCard . ' orang');
                $sheet->setCellValue('A' . ($footerRow + 7), '• Member tanpa Kartu RFID: ' . $this->membersWithoutCard . ' orang');
                
                // Statistik Kehadiran
                $sheet->setCellValue('A' . ($footerRow + 9), 'STATISTIK KEHADIRAN:');
                $sheet->setCellValue('A' . ($footerRow + 10), '• Member Aktif (hadir): ' . $this->activeMembers . ' orang');
                $sheet->setCellValue('A' . ($footerRow + 11), '• Member Tidak Aktif (tidak hadir) : ' . $this->inactiveMembers . ' orang');

                
                // Total dan Rata-rata Kehadiran
                $sheet->setCellValue('A' . ($footerRow + 13), 'TOTAL KEHADIRAN:');
                $sheet->setCellValue('A' . ($footerRow + 14), '• Total Seluruh Kehadiran: ' . $this->totalAttendance . ' kali');
                
                $avgAttendance = $this->totalMembers > 0 ? round($this->totalAttendance / $this->totalMembers, 2) : 0;
                $sheet->setCellValue('A' . ($footerRow + 15), '• Rata-rata per Member: ' . $avgAttendance . ' kali');
                
                // Persentase
                $activePercentage = $this->totalMembers > 0 ? round(($this->activeMembers / $this->totalMembers) * 100, 1) : 0;
                $cardPercentage = $this->totalMembers > 0 ? round(($this->membersWithCard / $this->totalMembers) * 100, 1) : 0;
                
                $sheet->setCellValue('A' . ($footerRow + 17), 'PERSENTASE:');
                $sheet->setCellValue('A' . ($footerRow + 18), '• Tingkat Keaktifan: ' . $activePercentage . '%');
                $sheet->setCellValue('A' . ($footerRow + 19), '• Member ber-Kartu: ' . $cardPercentage . '%');
                
                // Tanggal Export
                $sheet->setCellValue('A' . ($footerRow + 21), 'Tanggal Export: ' . Carbon::now()->format('d/m/Y H:i:s'));
                
                // Style untuk header statistik
                $sheet->getStyle('A' . $footerRow)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 12,
                        'color' => ['rgb' => '2E8B57']
                    ]
                ]);
                
                // Style untuk sub-header (TOTAL MEMBER, STATISTIK KEHADIRAN, dll)
                $subHeaders = [$footerRow + 4, $footerRow + 9, $footerRow + 13, $footerRow + 17];
                foreach ($subHeaders as $row) {
                    $sheet->getStyle('A' . $row)->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'size' => 11,
                            'color' => ['rgb' => '2E8B57']
                        ]
                    ]);
                }
                
                // Style untuk detail items dan tanggal export
                $detailRows = range($footerRow + 1, $footerRow + 22); // Disesuaikan rangenya
                foreach ($detailRows as $row) {
                    if (!in_array($row, array_merge([$footerRow], $subHeaders))) {
                        $sheet->getStyle('A' . $row)->applyFromArray([
                            'font' => [
                                'bold' => false,
                                'size' => 10,
                                'color' => ['rgb' => '666666']
                            ]
                        ]);
                    }
                }

                // Set print area dan page setup
                $sheet->getPageSetup()
                    ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT)
                    ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)
                    ->setFitToPage(true)
                    ->setFitToWidth(1)
                    ->setFitToHeight(0);

                // Set margins
                $sheet->getPageMargins()
                    ->setTop(0.75)
                    ->setRight(0.7)
                    ->setLeft(0.7)
                    ->setBottom(0.75);

                // Header dan footer untuk print
                $sheet->getHeaderFooter()
                    ->setOddHeader('&C&"Arial,Bold"LAPORAN ABSENSI MEMBER')
                    ->setOddFooter('&L&D &T&RHalaman &P dari &N');
            }
        ];
    }
}