@extends('layouts.app')
@section('title', 'Laporan Absensi Member')

{{-- STYLES: Custom CSS & Select2 Library --}}
@push('styles')
    {{-- CSS Inti Select2 --}}
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    {{-- Tema Select2 untuk Bootstrap 4 (Template Anda) --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.5.2/dist/select2-bootstrap4.min.css">
    <style>
        .step-legend { font-size: 1.1rem; font-weight: 600; color: #5a5c69; display: flex; align-items: center; }
        .step-legend .step-number { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; background-color: #1cc88a; color: white; font-size: 1rem; margin-right: 12px; }
        .select2-container--bootstrap4 .select2-selection--single { height: calc(1.5em + .75rem + 2px) !important; }
        .select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered { line-height: calc(1.5em + .75rem) !important; }
    </style>
@endpush


@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">@yield('title')</h1>
</div>

@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
@endif

<div class="row">
    {{-- Kolom Kiri: Form Generator --}}
    <div class="col-lg-8 mb-4">
        <div class="card shadow h-100">
            <form action="{{ route('members.export-attendance-report') }}" method="GET" id="attendance-form">
                <div class="card-body p-4">

                    {{-- STEP 1: PERIODE LAPORAN --}}
                    <fieldset class="mb-4">
                        <legend class="step-legend mb-3">
                            <span class="step-number">1</span> Pilih Periode Laporan
                        </legend>
                        <div class="p-3 rounded bg-light border mb-3">
                            <label class="font-weight-bold text-dark small">Pilih Cepat:</label>
                            <div class="btn-toolbar" role="toolbar">
                                <div class="btn-group btn-group-sm mr-2 mb-1" role="group">
                                    <button type="button" class="btn btn-outline-success" onclick="setThisWeek()"><i class="fas fa-calendar-week fa-fw mr-1"></i>Minggu Ini</button>
                                    <button type="button" class="btn btn-outline-success" onclick="setLastWeek()"><i class="fas fa-calendar-minus fa-fw mr-1"></i>Minggu Lalu</button>
                                </div>
                                <div class="btn-group btn-group-sm mb-1" role="group">
                                    <button type="button" class="btn btn-outline-success" onclick="setThisMonth()"><i class="fas fa-calendar-alt fa-fw mr-1"></i>Bulan Ini</button>
                                    <button type="button" class="btn btn-outline-success" onclick="setLastMonth()"><i class="fas fa-calendar fa-fw mr-1"></i>Bulan Lalu</button>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="start_date">Atau pilih manual tanggal mulai:</label>
                                <input type="date" name="start_date" id="start_date" class="form-control"
                                       value="{{ request('start_date', $defaultStartDate) }}" required>
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="end_date">Hingga tanggal selesai:</label>
                                <input type="date" name="end_date" id="end_date" class="form-control"
                                       value="{{ request('end_date', $defaultEndDate) }}" required>
                            </div>
                        </div>
                    </fieldset>

                    <hr class="my-4">

                    {{-- STEP 2: FILTER DATA --}}
                    <fieldset>
                        <legend class="step-legend mb-3">
                            <span class="step-number" style="background-color: #4e73df;">2</span> Terapkan Filter (Opsional)
                        </legend>
                        {{-- Baris Pertama Filter --}}
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="school_class_id">Filter berdasarkan Kelas</label>
                                <select name="school_class_id" id="school_class_id" class="form-control">
                                    <option value="">-- Semua Kelas --</option>
                                    @foreach($schoolClasses as $class)
                                        <option value="{{ $class->id }}" {{ request('school_class_id') == $class->id ? 'selected' : '' }}>
                                            {{ $class->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="member_id_select">Filter berdasarkan Nama</label>
                                <select name="member_id" id="member_id_select" class="form-control">
                                    <option value="">-- Pilih Member --</option>
                                    @foreach($members as $member)
                                        <option value="{{ $member->id }}" {{ request('member_id') == $member->id ? 'selected' : '' }}>
                                            {{ $member->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        {{-- Baris Kedua Filter (Tambahan untuk Waktu Tap) --}}
                        <div class="row">
                            <div class="col-md-6 form-group"> {{-- Gunakan col-md-6 agar lebarnya sama --}}
                                <label for="tap_time_category">Filter Waktu Tap</label>
                                <select name="tap_time_category" id="tap_time_category" class="form-control">
                                    <option value="">-- Semua Waktu --</option>
                                    <option value="pagi">Pagi (01:00 - 11:59)</option>
                                    <option value="siang">Siang (12:00 - 14:59)</option>
                                    <option value="sore">Sore (15:00 - 18:00)</option>
                                </select>
                            </div>
                            {{-- Kosongkan kolom sebelahnya jika hanya ada 1 filter di baris ini --}}
                            <div class="col-md-6"></div>
                        </div>
                    </fieldset>

                </div>

                <div class="card-footer text-right">
                    <a href="{{ route('members.attendance-report') }}" class="btn btn-secondary">
                        <i class="fas fa-sync-alt mr-1"></i>Reset Filter
                    </a>
                    <button type="submit" class="btn btn-success" id="generate-btn">
                        <i class="fas fa-download mr-1"></i>Generate Laporan Excel
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Kolom Kanan: Panduan & Informasi --}}
    <div class="col-lg-4 mb-4">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-book-open mr-2"></i>Panduan</h6>
            </div>
            <div class="card-body">
                <p>Gunakan form di samping untuk membuat laporan absensi dalam format Excel dengan kriteria yang Anda tentukan.</p>
                <hr>
                <div class="p-3 rounded" style="background-color: rgba(255, 193, 7, 0.1);">
                    <h6 class="font-weight-bold text-warning"><i class="fas fa-lightbulb mr-2"></i>Tips Pro</h6>
                    <p class="small mb-0">Untuk laporan mingguan, pastikan rentang tanggal Anda dimulai pada hari **Senin** dan berakhir pada hari **Minggu**.</p>
                </div>
                <div class="mt-3 p-3 rounded" style="background-color: rgba(40, 167, 69, 0.1);">
                    <h6 class="font-weight-bold text-success"><i class="fas fa-info-circle mr-2"></i>Catatan Penting</h6>
                    <p class="small mb-0">Member yang belum memiliki kartu RFID atau tidak pernah melakukan tap akan otomatis memiliki total kehadiran **0** pada laporan.</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

{{-- SCRIPTS: Logic, Date Functions, & Select2 Initialization --}}
@push('scripts')
{{-- Library untuk Select2 --}}
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
{{-- Library untuk Kalkulasi Tanggal --}}
<script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/moment.min.js"></script>

<script>
    // --- FUNGSI UNTUK MENGATUR TANGGAL OTOMATIS ---
    function setDateRange(start, end) {
        // Mengatur nilai input tanggal dengan format YYYY-MM-DD
        $('#start_date').val(start.format('YYYY-MM-DD'));
        $('#end_date').val(end.format('YYYY-MM-DD'));
    }
    // Fungsi yang dipanggil oleh tombol 'onclick'
    function setThisWeek() { setDateRange(moment().startOf('isoWeek'), moment().endOf('isoWeek')); }
    function setLastWeek() { setDateRange(moment().subtract(1, 'weeks').startOf('isoWeek'), moment().subtract(1, 'weeks').endOf('isoWeek')); }
    function setThisMonth() { setDateRange(moment().startOf('month'), moment().endOf('month')); }
    function setLastMonth() { setDateRange(moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')); }

    // --- KODE JQUERY SAAT HALAMAN SIAP ---
    $(document).ready(function() {
        // Inisialisasi Select2 untuk filter member (seperti sebelumnya)
        $('#member_id_select').select2({
            theme: 'bootstrap4',
            placeholder: 'Cari dan pilih member'
        });

        // Inisialisasi Select2 untuk filter kelas (agar tampilan konsisten)
         $('#school_class_id').select2({
            theme: 'bootstrap4'
        });

        // Inisialisasi Select2 untuk filter WAKTU TAP (agar tampilan konsisten)
         $('#tap_time_category').select2({
            theme: 'bootstrap4',
             minimumResultsForSearch: Infinity // Sembunyikan box pencarian karena opsinya sedikit
        });
    });
</script>
@endpush