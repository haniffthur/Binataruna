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

        // Terapkan filter yang diterima dari controller (sama seperti MembersExport)
       if (!empty($this->filters['member_id'])) {
    // Gunakan 'where' dengan ID, bukan 'like' dengan nama
    $members->where('id', $this->filters['member_id']); 
}

        if (!empty($this->filters['school_class_id'])) {
            $members->where('school_class_id', $this->filters['school_class_id']);
        }

        if (!empty($this->filters['join_date'])) {
            $members->whereDate('join_date', $this->filters['join_date']);
        }

        // Urutkan berdasarkan kelas terlebih dahulu, kemudian nama member secara alfabetis
        // (sama seperti MembersExport)
        $result = $members->get()->sortBy([
            ['schoolClass.name', 'asc'],  // Urutkan berdasarkan nama kelas
            ['name', 'asc']               // Kemudian urutkan berdasarkan nama member
        ]);

        // Hitung attendance untuk setiap member
        $membersWithAttendance = $result->map(function ($member) {
            // Hitung jumlah tap granted dalam periode
            $attendanceCount = 0;
            
            if ($member->masterCard && $member->masterCard->id) {
                $this->membersWithCard++;
                $attendanceCount = TapLog::where('master_card_id', $member->masterCard->id)
                    ->where('status', 1) // Status granted
                    ->whereBetween('tapped_at', [
                        $this->startDate->startOfDay(),
                        $this->endDate->endOfDay()
                    ])
                    ->count();
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
     * Header untuk Excel dengan styling profesional
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
            strtoupper($member->name), // Nama dalam huruf kapital untuk konsistensi
            $member->schoolClass->name ?? 'Belum Ditentukan',
            $member->masterCard->cardno ?? 'Belum Terdaftar',
            $member->attendance_count ?? 0 // Jumlah kehadiran
        ];
    }

    /**
     * Styling untuk worksheet (disesuaikan dengan MembersExport)
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
                    'startColor' => ['rgb' => '2E8B57'] // Hijau untuk attendance report
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
                'font' => [
                    'size' => 10
                ]
            ],
            // Style untuk kolom Nama (kolom B)
            'B:B' => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'font' => [
                    'size' => 10
                ]
            ],
            // Style untuk kolom Kelas (kolom C)
            'C:C' => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'font' => [
                    'size' => 10
                ]
            ],
            // Style untuk kolom RFID (kolom D)
            'D:D' => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'font' => [
                    'size' => 10,
                    'name' => 'Consolas' // Font monospace untuk nomor kartu
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
                    'color' => ['rgb' => '2E8B57'] // Hijau untuk highlight attendance
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

                // Zebra striping untuk baris data (alternating colors)
                for ($i = 2; $i <= $highestRow; $i++) {
                    if ($i % 2 == 0) {
                        $sheet->getStyle('A' . $i . ':E' . $i)->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'F2F2F2'] // Abu-abu terang
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
                
                // Total Member
                $sheet->setCellValue('A' . ($footerRow + 3), 'TOTAL MEMBER:');
                $sheet->setCellValue('A' . ($footerRow + 4), '• Total Member: ' . $this->totalMembers . ' orang');
                $sheet->setCellValue('A' . ($footerRow + 5), '• Member dengan Kartu RFID: ' . $this->membersWithCard . ' orang');
                $sheet->setCellValue('A' . ($footerRow + 6), '• Member tanpa Kartu RFID: ' . $this->membersWithoutCard . ' orang');
                
                // Statistik Kehadiran
                $sheet->setCellValue('A' . ($footerRow + 8), 'STATISTIK KEHADIRAN:');
                $sheet->setCellValue('A' . ($footerRow + 9), '• Member Aktif (hadir): ' . $this->activeMembers . ' orang');
                $sheet->setCellValue('A' . ($footerRow + 10), '• Member Tidak Aktif(tidak hadir) : ' . $this->inactiveMembers . ' orang');

                
                // Total dan Rata-rata Kehadiran
                $sheet->setCellValue('A' . ($footerRow + 12), 'TOTAL KEHADIRAN:');
                $sheet->setCellValue('A' . ($footerRow + 13), '• Total Seluruh Kehadiran: ' . $this->totalAttendance . ' kali');
                
                $avgAttendance = $this->totalMembers > 0 ? round($this->totalAttendance / $this->totalMembers, 2) : 0;
                $sheet->setCellValue('A' . ($footerRow + 14), '• Rata-rata per Member: ' . $avgAttendance . ' kali');
                
                // Persentase
                $activePercentage = $this->totalMembers > 0 ? round(($this->activeMembers / $this->totalMembers) * 100, 1) : 0;
                $cardPercentage = $this->totalMembers > 0 ? round(($this->membersWithCard / $this->totalMembers) * 100, 1) : 0;
                
                $sheet->setCellValue('A' . ($footerRow + 16), 'PERSENTASE:');
                $sheet->setCellValue('A' . ($footerRow + 17), '• Tingkat Keaktifan: ' . $activePercentage . '%');
                $sheet->setCellValue('A' . ($footerRow + 18), '• Member ber-Kartu: ' . $cardPercentage . '%');
                
                // Tanggal Export
                $sheet->setCellValue('A' . ($footerRow + 20), 'Tanggal Export: ' . Carbon::now()->format('d/m/Y H:i:s'));
                
                // Style untuk header statistik
                $sheet->getStyle('A' . $footerRow)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 12,
                        'color' => ['rgb' => '2E8B57']
                    ]
                ]);
                
                // Style untuk sub-header (TOTAL MEMBER, STATISTIK KEHADIRAN, dll)
                $subHeaders = [$footerRow + 3, $footerRow + 8, $footerRow + 13, $footerRow + 17];
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
                $detailRows = range($footerRow + 1, $footerRow + 21);
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