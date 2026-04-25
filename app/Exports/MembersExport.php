<?php

namespace App\Exports;

use App\Models\Member;
use App\Models\TapLog; // Pastikan model TapLog di-import
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use Illuminate\Http\Request;
use Carbon\Carbon;

class MembersExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithTitle, WithEvents
{
    protected $filters;
    protected $memberCounter = 0;
    protected $totalMembers = 0;

    public function __construct(array $filters)
    {
        $this->filters = $filters;
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
                'master_card_id',
                'join_date' // <-- TAMBAHKAN INI AGAR BISA DITAMPILKAN
            );

        // Terapkan filter yang diterima dari controller
        if (!empty($this->filters['name'])) {
            $members->where('name', 'like', '%' . $this->filters['name'] . '%');
        }

        if (!empty($this->filters['school_class_id'])) {
            $members->where('school_class_id', $this->filters['school_class_id']);
        }

        if (!empty($this->filters['join_date'])) {
            $members->whereDate('join_date', $this->filters['join_date']);
        }

        // Urutkan berdasarkan kelas terlebih dahulu, kemudian nama member secara alfabetis
        $members = $members->get()->sortBy([
            ['schoolClass.name', 'asc'],  // Urutkan berdasarkan nama kelas
            ['name', 'asc']               // Kemudian urutkan berdasarkan nama member
        ]);

        // Reset counter dan hitung total member
        $this->memberCounter = 0;
        $this->totalMembers = $members->count();

        return $members;
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
            'Tanggal Bergabung', // <-- KOLOM BARU
            'Tap Pertama Kali'   // <-- KOLOM BARU
        ];
    }

    /**
     * Mapping data untuk setiap baris
     */
    public function map($member): array
    {
        $this->memberCounter++;
        
        // 1. Format Tanggal Bergabung
        $joinDate = $member->join_date ? Carbon::parse($member->join_date)->format('d M Y') : '-';

        // 2. Logika Tap Pertama Kali
        $firstTap = 'Belum pernah tap';
        if ($member->master_card_id) {
            // Cari log tap paling lama (ASC) dari kartu ini
            $tap = TapLog::where('master_card_id', $member->master_card_id)
                        ->orderBy('tapped_at', 'asc')
                        ->first();
                        
            if ($tap) {
                $firstTap = Carbon::parse($tap->tapped_at)->format('d M Y, H:i:s');
            }
        } else {
            $firstTap = 'Belum memiliki kartu';
        }
        
        return [
            $this->memberCounter,
            strtoupper($member->name), // Nama dalam huruf kapital untuk konsistensi
            $member->schoolClass->name ?? 'Belum Ditentukan',
            $member->masterCard->cardno ?? 'Belum Terdaftar',
            $joinDate, // <-- MAPPING BARU
            $firstTap  // <-- MAPPING BARU
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
                    'startColor' => ['rgb' => '4472C4'] // Biru profesional
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
            // Style untuk kolom Tanggal Bergabung (kolom E)
            'E:E' => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'font' => [
                    'size' => 10
                ]
            ],
            // Style untuk kolom Tap Pertama (kolom F)
            'F:F' => [
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'font' => [
                    'size' => 10
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
            'E' => 20,  // Tanggal Bergabung
            'F' => 25,  // Tap Pertama Kali
        ];
    }

    /**
     * Judul sheet
     */
    public function title(): string
    {
        return 'Data Member';
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
                
                // Border untuk seluruh data (ubah batasnya dari D ke F)
                $sheet->getStyle('A1:F' . $highestRow)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000']
                        ]
                    ]
                ]);

                // Zebra striping untuk baris data (ubah batasnya dari D ke F)
                for ($i = 2; $i <= $highestRow; $i++) {
                    if ($i % 2 == 0) {
                        $sheet->getStyle('A' . $i . ':F' . $i)->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'F2F2F2'] // Abu-abu terang
                            ]
                        ]);
                    }
                }

                // Freeze panes (membekukan baris header)
                $sheet->freezePane('A2');

                // Auto filter untuk header (ubah batasnya dari D ke F)
                $sheet->setAutoFilter('A1:F' . $highestRow);

                // Menambahkan informasi di bagian bawah
                $footerRow = $highestRow + 2;
                $sheet->setCellValue('A' . $footerRow, 'Total Member: ' . $this->totalMembers);
                $sheet->setCellValue('A' . ($footerRow + 1), 'Tanggal Export: ' . Carbon::now()->format('d/m/Y H:i:s'));
                
                // Style untuk footer
                $sheet->getStyle('A' . $footerRow . ':A' . ($footerRow + 1))->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 10,
                        'color' => ['rgb' => '666666']
                    ]
                ]);

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
                    ->setOddHeader('&C&"Arial,Bold"DATA MEMBER SEKOLAH')
                    ->setOddFooter('&L&D &T&RHalaman &P dari &N');
            }
        ];
    }
}