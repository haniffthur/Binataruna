@extends('layouts.app')
@section('title', 'Data Transaksi')
@section('content')
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            @if($viewType == 'status')
                Laporan Status Pembayaran
            @else
                Riwayat Transaksi Masuk
            @endif
        </h1>
    </div>

    {{-- CARD FILTER --}}
    <div class="card shadow mb-4 border-left-primary">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-filter mr-2"></i>Filter Data</h6>
        </div>
        <div class="card-body">
            <form action="{{ route('transactions.index') }}" method="GET" id="transaction-filter-form">
                
                {{-- Input Hidden untuk menjaga tab yang aktif (member/non-member) di mode history --}}
                @if($viewType == 'history')
                    <input type="hidden" name="type" value="{{ request('type', 'all') }}">
                @endif

                <div class="row">
                    {{-- 1. PILIH TAMPILAN DATA (KUNCI PERUBAHAN) --}}
                    <div class="col-md-3 form-group">
                        <label class="font-weight-bold text-dark">Tampilan Data</label>
                        <select name="view_type" id="view_type" class="form-control border-primary" style="background-color: #e3f2fd;">
                            {{-- Opsi 1: History --}}
                            <option value="history" {{ request('view_type', 'history') == 'history' ? 'selected' : '' }}>
                                📄 Riwayat Transaksi (Sudah Bayar)
                            </option>
                            {{-- Opsi 2: Status --}}
                            <option value="status" {{ request('view_type') == 'status' ? 'selected' : '' }}>
                                👥 Cek Status Pembayaran (Semua Member)
                            </option>
                        </select>
                    </div>

                    <div class="col-md-3 form-group">
                        <label>Periode Waktu</label>
                        <select name="period" id="period" class="form-control">
                            <option value="all_time" {{ request('period', 'all_time') == 'all_time' ? 'selected' : '' }}>Semua Waktu</option>
                            <option value="today" {{ request('period') == 'today' ? 'selected' : '' }}>Hari Ini</option>
                            <option value="this_week" {{ request('period') == 'this_week' ? 'selected' : '' }}>Minggu Ini</option>
                            <option value="this_month" {{ request('period') == 'this_month' ? 'selected' : '' }}>Bulan Ini</option>
                            <option value="custom" {{ request('period') == 'custom' ? 'selected' : '' }}>Pilih Rentang</option>
                        </select>
                    </div>

                    <div class="col-md-3 form-group">
                        <label>Nama Pelanggan / Member</label>
                        <input type="text" name="name" id="name" class="form-control" placeholder="Cari nama..." value="{{ request('name') }}">
                    </div>
                    
                    <div class="col-md-3 form-group">
                        <label>Filter Kelas</label>
                        <select name="class_id" id="class_id" class="form-control">
                            <option value="">Semua Kelas</option>
                            @foreach($schoolClasses as $class)
                                <option value="{{ $class->id }}" {{ request('class_id') == $class->id ? 'selected' : '' }}>{{ $class->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="row align-items-end">
                    <div class="col-md-9">
                        <div id="custom-date-range" style="{{ request('period') == 'custom' ? '' : 'display: none;' }}">
                            <div class="row">
                                <div class="col-md-4 form-group mb-md-0">
                                    <label>Dari Tanggal</label>
                                    <input type="date" name="start_date" id="start_date" class="form-control" value="{{ request('start_date') }}">
                                </div>
                                <div class="col-md-4 form-group mb-md-0">
                                    <label>Sampai Tanggal</label>
                                    <input type="date" name="end_date" id="end_date" class="form-control" value="{{ request('end_date') }}">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 d-flex justify-content-end">
                        <a href="{{ route('transactions.index') }}" class="btn btn-secondary mr-2">Reset</a>
                        <button type="submit" class="btn btn-primary mr-2">Terapkan Filter</button>
                        
                        {{-- Tombol Export akan mengarah ke route dengan parameter filter saat ini --}}
                        <a href="{{ route('transactions.export.excel', request()->all()) }}" id="export-excel-btn" class="btn btn-success">
                            <i class="fas fa-file-excel"></i> Export Excel
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- CARD TABEL DATA --}}
    <div class="card shadow mb-4">
        <div class="card-body">
            
            {{-- LOGIKA TAMPILAN TABEL --}}
            @if($viewType == 'status')
                {{-- TABEL MODE 2: STATUS PEMBAYARAN MEMBER (SEMUA MEMBER) --}}
                <div class="alert alert-info border-left-info" role="alert">
                    <i class="fas fa-info-circle mr-1"></i>
                    Menampilkan <strong>status pembayaran seluruh member</strong> pada periode yang dipilih.
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" width="100%" cellspacing="0">
                        <thead class="thead-light">
                            <tr>
                                <th width="5%">No</th>
                                <th>Nama Member</th>
                                <th>Kelas</th>
                                <th class="text-center">Status Pembayaran</th>
                                <th>Tanggal Bayar</th>
                                <th>Nominal</th>
                                <th class="text-center" width="10%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($paymentStatusData as $index => $member)
                                @php
                                    $transaction = $member->transactions->first(); 
                                @endphp
                                <tr>
                                    <td>{{ $index + $paymentStatusData->firstItem() }}</td>
                                    <td class="font-weight-bold">{{ $member->name }}</td>
                                    <td>{{ $member->schoolClass->name ?? '-' }}</td>
                                    <td class="text-center">
                                        @if($transaction)
                                            <span class="badge badge-success px-3 py-2">LUNAS</span>
                                        @else
                                            <span class="badge badge-danger px-3 py-2">BELUM BAYAR</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($transaction)
                                            {{ \Carbon\Carbon::parse($transaction->transaction_date)->format('d M Y, H:i') }}
                                        @else
                                            <span class="text-muted small">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($transaction)
                                            Rp {{ number_format($transaction->total_amount, 0, ',', '.') }}
                                        @else
                                            <span class="text-muted small">-</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if(!$transaction)
                                            <a href="{{ route('transactions.member.create') }}" class="btn btn-sm btn-primary shadow-sm" title="Bayar Sekarang">
                                                <i class="fas fa-cash-register"></i> Bayar
                                            </a>
                                        @else
                                            <span class="text-success"><i class="fas fa-check-circle fa-lg"></i></span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">Tidak ada data member ditemukan.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    {{ $paymentStatusData->links('pagination::bootstrap-5') }}
                </div>

            @else
                {{-- TABEL MODE 1: RIWAYAT TRANSAKSI (DEFAULT - HANYA YANG SUDAH BAYAR) --}}
                
                <ul class="nav nav-tabs mb-3">
                    <li class="nav-item"><a class="nav-link {{ request('type', 'all') == 'all' ? 'active' : '' }}" href="{{ route('transactions.index', array_merge(request()->except('type'), ['type' => 'all'])) }}">Semua Transaksi</a></li>
                    <li class="nav-item"><a class="nav-link {{ request('type') == 'member' ? 'active' : '' }}" href="{{ route('transactions.index', array_merge(request()->except('type'), ['type' => 'member'])) }}">Transaksi Member</a></li>
                    <li class="nav-item"><a class="nav-link {{ request('type') == 'non-member' ? 'active' : '' }}" href="{{ route('transactions.index', array_merge(request()->except('type'), ['type' => 'non-member'])) }}">Transaksi Non-Member</a></li>
                </ul>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped" width="100%" cellspacing="0">
                        <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>ID Transaksi</th>
                                <th>Nama Pelanggan</th>
                                <th>Tipe</th>
                                <th>Item / Kelas</th>
                                <th>Total Bayar</th>
                                <th>Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($transactions as $index => $transaction)
                                <tr>
                                    <td>{{ $index + $transactions->firstItem() }}</td>
                                    <td>#{{ $transaction->id }}</td>
                                    <td>{{ $transaction->customer_name ?? 'Tamu' }}</td>
                                    <td>
                                        @if($transaction->transaction_type == 'Member')
                                            <span class="badge badge-success">Member</span>
                                        @else
                                            <span class="badge badge-warning">Non-Member</span>
                                        @endif
                                    </td>
                                    <td>
                                        <strong>{{ $transaction->item_name ?? '-' }}</strong>
                                        @if(stripos($transaction->item_name, 'CUTI') !== false)
                                            <span class="badge badge-warning ml-1 text-dark" style="font-size: 0.65em;">CUTI</span>
                                        @endif
                                    </td>
                                    <td>Rp {{ number_format($transaction->total_amount, 0, ',', '.') }}</td>
                                    <td>{{ \Carbon\Carbon::parse($transaction->transaction_date)->format('d M Y, H:i') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        <i class="fas fa-search mb-2" style="font-size: 20px;"></i><br>
                                        Tidak ada riwayat transaksi yang cocok dengan filter Anda.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    {{ $transactions->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const filterForm = document.getElementById('transaction-filter-form');
        const exportBtn = document.getElementById('export-excel-btn');
        const periodSelect = document.getElementById('period');
        const customDateRange = document.getElementById('custom-date-range');
        const startDateInput = document.getElementById('start_date');
        const endDateInput = document.getElementById('end_date');

        // Logic Update URL Export (Real-time dari Form)
        function updateExportUrl() {
            if(filterForm && exportBtn) {
                const formData = new FormData(filterForm);
                const params = new URLSearchParams(formData).toString();
                // Kirim semua parameter form ke route export
                exportBtn.href = `{{ route('transactions.export.excel') }}?${params}`;
            }
        }

        if (filterForm) {
            filterForm.addEventListener('change', updateExportUrl);
            filterForm.addEventListener('input', updateExportUrl);
            updateExportUrl(); 
        }

        if (periodSelect && customDateRange) {
            function toggleCustomDate() {
                if (periodSelect.value === 'custom') {
                    customDateRange.style.display = 'block';
                    if(startDateInput) startDateInput.required = true;
                    if(endDateInput) endDateInput.required = true;
                } else {
                    customDateRange.style.display = 'none';
                    if(startDateInput) startDateInput.required = false;
                    if(endDateInput) endDateInput.required = false;
                }
                updateExportUrl();
            }
            periodSelect.addEventListener('change', toggleCustomDate);
            toggleCustomDate();
        }
    });
</script>
@endpush