@extends('layouts.app')

@section('title', 'Manajemen Kartu RFID')

@section('content')
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">@yield('title')</h1>
        <a href="{{ route('master-cards.create') }}" class="btn btn-primary btn-sm shadow-sm">
            <i class="fas fa-plus fa-sm text-white-50"></i> Tambah Kartu Baru
        </a>
    </div>

    {{-- Cards for summary data --}}
    <div class="row">
        {{-- Total Cards Card --}}
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Kartu RFID
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $totalCards }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-credit-card fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Available Cards Card --}}
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Kartu Tersedia
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $availableCards }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Assigned Cards Card --}}
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Kartu Digunakan
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $assignedCards }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    {{-- End of Cards for summary data --}}

    {{-- Filter Form --}}
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filter Kartu</h6>
        </div>
        <div class="card-body">
            <form action="{{ route('master-cards.index') }}" method="GET" class="form-inline">
                <div class="form-group mb-2 mr-2">
                    <label for="search" class="sr-only">Cari UID Kartu</label>
                    <input type="text" class="form-control" id="search" name="search" placeholder="Cari UID Kartu..." value="{{ $search }}">
                </div>
                <div class="form-group mb-2 mr-2">
                    <label for="card_type" class="sr-only">Tipe Kartu</label>
                    <select class="form-control" id="card_type" name="card_type">
                        <option value="">Semua Tipe</option>
                        <option value="member" {{ $cardType == 'member' ? 'selected' : '' }}>Member</option>
                        <option value="coach" {{ $cardType == 'coach' ? 'selected' : '' }}>Pelatih</option>
                        <option value="staff" {{ $cardType == 'staff' ? 'selected' : '' }}>Staff</option>
                    </select>
                </div>
                <div class="form-group mb-2 mr-2">
                    <label for="assignment_status" class="sr-only">Status</label>
                    <select class="form-control" id="assignment_status" name="assignment_status">
                        <option value="">Semua Status</option>
                        <option value="available" {{ $assignmentStatus == 'available' ? 'selected' : '' }}>Tersedia</option>
                        <option value="assigned" {{ $assignmentStatus == 'assigned' ? 'selected' : '' }}>Digunakan</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-info mb-2 mr-2">Filter</button>
                <a href="{{ route('master-cards.index') }}" class="btn btn-secondary mb-2">Reset</a>
            </form>
        </div>
    </div>
    {{-- End Filter Form --}}

    <div class="card shadow mb-4">
        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif

            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>UID Kartu</th>
                            <th>Tipe Kartu</th>
                            <th>Status</th>
                            <th>Dibuat Pada</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cards as $index => $card)
                            <tr>
                                <td>{{ $index + $cards->firstItem() }}</td>
                                <td><span class="font-weight-bold">{{ $card->cardno }}</span></td>
                                <td>
                                    @if($card->card_type == 'member')
                                        <span class="badge badge-primary">Member</span>
                                    @elseif($card->card_type == 'coach')
                                        <span class="badge badge-info">Pelatih</span>
                                    @else
                                        <span class="badge badge-dark">Staff</span>
                                    @endif
                                </td>
                                <td>
                                    @if($card->assignment_status == 'available')
                                        <span class="badge badge-success">Tersedia</span>
                                    @else
                                        <span class="badge badge-warning">Digunakan</span>
                                    @endif
                                </td>
                                <td>{{ $card->created_at->format('d M Y') }}</td>
                                <td>
                                    <a href="{{ route('master-cards.edit', $card->id) }}" class="btn btn-warning btn-sm">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form action="{{ route('master-cards.destroy', $card->id) }}" method="POST" class="d-inline"
                                        onsubmit="return confirm('Yakin ingin hapus kartu ini? Jika kartu sedang digunakan, pemiliknya akan kehilangan akses kartu.')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-danger btn-sm">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-3">Tidak ada data kartu</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $cards->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
@endsection