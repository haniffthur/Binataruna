<?php

namespace App\Exports;

use App\Models\Member;
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
                'master_card_id'
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
            'Kartu RFID'
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
            $member->masterCard->cardno ?? 'Belum Terdaftar'
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
                
                // Border untuk seluruh data
                $sheet->getStyle('A1:D' . $highestRow)->applyFromArray([
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
                        $sheet->getStyle('A' . $i . ':D' . $i)->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'F2F2F2'] // Abu-abu terang
                            ]
                        ]);
                    }
                }

                // Freeze panes (membekukan baris header)
                $sheet->freezePane('A2');

                // Auto filter untuk header
                $sheet->setAutoFilter('A1:D' . $highestRow);

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