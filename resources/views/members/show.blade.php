@extends('layouts.app') 

@section('title', 'Detail Member: ' . $member->name)

@section('content')
    {{-- Header --}}
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            Detail Member: {{ $member->name }}
            @if ($member->is_active)
                <span class="badge badge-success ml-2" style="font-size: 0.6em; vertical-align: middle;">Aktif</span>
            @else
                <span class="badge badge-danger ml-2" style="font-size: 0.6em; vertical-align: middle;">Nonaktif</span>
            @endif
        </h1>
        <a href="{{ route('members.index') }}" class="btn btn-secondary btn-sm shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50"></i> Kembali ke Daftar Member
        </a>
    </div>

    <div class="row">
        {{-- Kolom Kiri: Detail Profil --}}
        <div class="col-lg-7 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Informasi Profil</h6>
                    <a href="{{ route('members.edit', $member->id) }}" class="btn btn-primary btn-sm">
                        <i class="fas fa-edit fa-sm"></i> Edit Data
                    </a>
                </div>
                <div class="card-body">
                    <div class="row">
                        {{-- Foto --}}
                        <div class="col-md-4 text-center mb-3 mb-md-0">
                            @if($member->photo)
                                <img src="{{ asset('storage/' . $member->photo) }}" class="img-fluid img-thumbnail rounded-circle mb-3" style="width: 150px; height: 150px; object-fit: cover;" alt="Foto Profil">
                                <a href="{{ route('members.download.photo', $member->id) }}" class="btn btn-sm btn-outline-primary btn-block">
                                    <i class="fas fa-download fa-sm"></i> Download Foto
                                </a>
                            @else
                                <img src="https://via.placeholder.com/150" class="img-fluid img-thumbnail rounded-circle mb-3" style="width: 150px; height: 150px; object-fit: cover;" alt="Tidak ada foto">
                                <p class="small text-muted">Tidak ada foto</p>
                            @endif
                        </div>
                        
                        {{-- Tabel Detail --}}
                        <div class="col-md-8">
                            <div class="table-responsive">
                                <table class="table table-borderless table-sm">
                                    <tbody>
                                        <tr><td style="width: 140px;" class="text-muted">Nama Lengkap</td><td class="font-weight-bold">{{ $member->name }}</td></tr>
                                        <tr><td class="text-muted">Nama Panggilan</td><td>{{ $member->nickname ?? '-' }}</td></tr>
                                        <tr><td class="text-muted">NIS / NISNAS</td><td>{{ $member->nis ?? '-' }} / {{ $member->nisnas ?? '-' }}</td></tr>
                                        <tr><td class="text-muted">Kelas</td><td class="font-weight-bold">{{ $member->schoolClass->name ?? '-' }}</td></tr>
                                        <tr><td class="text-muted">No. Telepon</td><td>{{ $member->phone_number ?? '-' }}</td></tr>
                                        <tr><td class="text-muted">Tanggal Lahir</td><td>{{ $member->date_of_birth ? \Carbon\Carbon::parse($member->date_of_birth)->translatedFormat('d F Y') : '-' }}</td></tr>
                                        <tr><td class="text-muted">Nama Orang Tua</td><td>{{ $member->parent_name ?? '-' }}</td></tr>
                                        <tr><td class="text-muted">Alamat</td><td>{{ $member->address ?? '-' }}</td></tr>
                                        <tr><td class="text-muted">Tgl Bergabung</td><td>{{ \Carbon\Carbon::parse($member->join_date)->translatedFormat('d F Y') }}</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Kolom Kanan: Kartu & Aturan --}}
        <div class="col-lg-5 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Status & Kartu RFID</h6>
                </div>
                <div class="card-body">
                    {{-- Kartu RFID --}}
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted font-weight-bold">Kartu RFID:</span>
                        @if($member->masterCard)
                            <div>
                                <span class="font-weight-bold mr-2">{{ $member->masterCard->cardno }}</span>
                                <a href="{{ route('master-cards.edit', $member->masterCard->id) }}" class="text-info" title="Lihat Detail Kartu"><i class="fas fa-external-link-alt"></i></a>
                            </div>
                        @else
                            <span class="text-danger font-italic small">Belum Terdaftar</span>
                        @endif
                    </div>

                    {{-- Status Member --}}
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="text-muted font-weight-bold">Status Member:</span>
                        @if ($member->is_active)
                            <span class="badge badge-success px-3 py-2">Aktif</span>
                        @else
                            <span class="badge badge-danger px-3 py-2">Nonaktif (Cuti)</span>
                        @endif
                    </div>
                    
                    <hr>
                    <h6 class="font-weight-bold small text-uppercase text-gray-600 mb-3">Sisa Kuota Tap</h6>
                    
                    {{-- Progress Tap Harian --}}
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Tap Hari Ini</span>
                            @if ($tapsData['max_daily'] == 'N/A')
                                <span class="text-muted">Tidak Dibatasi</span>
                            @elseif ($tapsData['max_daily'] == 'Tak Terbatas')
                                <span class="text-success font-weight-bold">∞</span>
                            @else
                                <span class="font-weight-bold">{{ $tapsData['used_daily'] }} / {{ $tapsData['max_daily'] }}</span>
                            @endif
                        </div>
                        @if (is_numeric($tapsData['max_daily']) && $tapsData['max_daily'] > 0)
                            <div class="progress progress-sm">
                                @php $pctDaily = min(100, ($tapsData['used_daily'] / $tapsData['max_daily']) * 100); @endphp
                                <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $pctDaily }}%"></div>
                            </div>
                            <div class="text-right small text-muted mt-1">Sisa: {{ $tapsData['remaining_daily'] }}</div>
                        @endif
                    </div>

                    {{-- Progress Tap Bulanan --}}
                    <div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Tap Bulan Ini</span>
                            @if ($tapsData['max_monthly'] == 'N/A')
                                <span class="text-muted">Tidak Dibatasi</span>
                            @elseif ($tapsData['max_monthly'] == 'Tak Terbatas')
                                <span class="text-success font-weight-bold">∞</span>
                            @else
                                <span class="font-weight-bold">{{ $tapsData['used_monthly'] }} / {{ $tapsData['max_monthly'] }}</span>
                            @endif
                        </div>
                        @if (is_numeric($tapsData['max_monthly']) && $tapsData['max_monthly'] > 0)
                            <div class="progress progress-sm">
                                @php $pctMonthly = min(100, ($tapsData['used_monthly'] / $tapsData['max_monthly']) * 100); @endphp
                                <div class="progress-bar bg-info" role="progressbar" style="width: {{ $pctMonthly }}%"></div>
                            </div>
                            <div class="text-right small text-muted mt-1">Sisa: {{ $tapsData['remaining_monthly'] }}</div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Detail Aturan --}}
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Detail Aturan Akses</h6>
                </div>
                <div class="card-body">
                    @if ($member->rule_type == 'custom')
                        <p class="font-weight-bold text-info small mb-2"><i class="fas fa-star fa-fw mr-1"></i>Aturan Kustom (Spesifik)</p>
                    @elseif($member->accessRule)
                        <p class="font-weight-bold text-dark small mb-2"><i class="fas fa-layer-group fa-fw mr-1"></i>Template: {{ $member->accessRule->name }}</p>
                    @else
                        <p class="text-muted small mb-2">Tidak ada aturan spesifik.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- RIWAYAT TAPPING --}}
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Riwayat Tapping</h6>
        </div>
        <div class="card-body">
            
            {{-- FORM FILTER & DOWNLOAD EXCEL --}}
            <form action="{{ route('members.show', $member->id) }}" method="GET" class="mb-4">
                <div class="form-row align-items-end">
                    <div class="col-md-3 mb-2">
                        <label class="small font-weight-bold text-muted mb-1">Dari Tanggal</label>
                        <input type="date" name="start_date" class="form-control form-control-sm" value="{{ request('start_date') }}">
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="small font-weight-bold text-muted mb-1">Sampai Tanggal</label>
                        <input type="date" name="end_date" class="form-control form-control-sm" value="{{ request('end_date') }}">
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="small font-weight-bold text-muted mb-1">Status</label>
                        <select name="status" class="form-control form-control-sm">
                            <option value="all">Semua</option>
                            <option value="1" {{ request('status') == '1' ? 'selected' : '' }}>Berhasil</option>
                            <option value="0" {{ request('status') == '0' ? 'selected' : '' }}>Gagal</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-2 text-right">
                        <button type="submit" class="btn btn-primary btn-sm mr-1">
                            <i class="fas fa-filter fa-sm"></i> Filter
                        </button>
                        <a href="{{ route('members.show', $member->id) }}" class="btn btn-light btn-sm border mr-1" title="Reset Filter">
                            <i class="fas fa-sync-alt fa-sm"></i>
                        </a>
                        
                        {{-- TOMBOL DOWNLOAD EXCEL (BARU) --}}
                        <a href="{{ route('members.export-log', array_merge(['member' => $member->id], request()->all())) }}" class="btn btn-success btn-sm shadow-sm">
                            <i class="fas fa-file-excel fa-sm text-white-50 mr-1"></i> Download Excel
                        </a>
                    </div>
                </div>
            </form>

            {{-- TABEL DATA --}}
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover" width="100%" cellspacing="0">
                    <thead class="thead-light">
                        <tr>
                            <th style="width: 25%;">Waktu Tap</th>
                            <th style="width: 15%;">Status</th>
                            <th>Pesan / Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($tapLogs as $log)
                            <tr>
                                <td>{{ $log->tapped_at->translatedFormat('d M Y, H:i:s') }}</td>
                                <td>
                                    @if($log->status == 1)
                                        <span class="badge badge-success px-2 py-1"><i class="fas fa-check mr-1"></i>GRANTED</span>
                                    @else
                                        <span class="badge badge-danger px-2 py-1"><i class="fas fa-times mr-1"></i>DENIED</span>
                                    @endif
                                </td>
                                <td>{{ $log->message }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">
                                    <img src="https://via.placeholder.com/64" alt="Empty" class="mb-2" style="opacity: 0.5;"><br>
                                    Tidak ada riwayat tapping yang ditemukan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- PAGINATION --}}
            <div class="mt-3 d-flex justify-content-end">
                {{ $tapLogs->links() }} 
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(function () {
        $('[data-toggle="tooltip"]').tooltip()
    })
</script>
@endpush