@extends('layouts.app')
@section('title', 'Tambah Kelas Baru')
@section('content')
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">@yield('title')</h1>
        <a href="{{ route('classes.index') }}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left fa-sm"></i>
            Kembali</a>
    </div>
    <div class="card shadow mb-4">
        <div class="card-body">
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>@endif
            <form action="{{ route('classes.store') }}" method="POST">
                @csrf
                <div class="form-group"><label>Nama Kelas</label><input type="text" name="name" class="form-control"
                        value="{{ old('name') }}" required></div>
                <div class="form-group"><label>Deskripsi</label><textarea name="description"
                        class="form-control">{{ old('description') }}</textarea></div>
                <div class="form-group"><label>Harga</label><input type="number" name="price" class="form-control"
                        value="{{ old('price') }}" required min="0"></div>
                <div class="form-group">
                    <label for="access_rule_id">Aturan Akses Default (Opsional)</label>
                    <select name="access_rule_id" id="access_rule_id" class="form-control">
                        <option value="">-- Tidak Ada Aturan Otomatis --</option>
                        @foreach($accessRules as $rule)
                            <option value="{{ $rule->id }}" {{ (old('access_rule_id', $class->access_rule_id ?? '') == $rule->id) ? 'selected' : '' }}>
                                {{ $rule->name }}
                            </option>
                        @endforeach
                    </select>
                    <small class="form-text text-muted">
                        Jika dipilih, aturan ini akan otomatis terpilih saat member mendaftar kelas ini.
                    </small>
                </div>
                <button type="submit" class="btn btn-primary">Simpan Kelas</button>
            </form>
        </div>
    </div>
@endsection