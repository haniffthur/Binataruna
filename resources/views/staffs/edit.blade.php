@extends('layouts.app')
@section('title', 'Edit Staff: ' . $staff->name)
@section('content')
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">@yield('title')</h1>
        <a href="{{ route('staffs.index') }}" class="btn btn-secondary btn-sm shadow-sm"><i class="fas fa-arrow-left fa-sm"></i> Kembali</a>
    </div>
    <div class="card shadow mb-4">
        <div class="card-body">
            @if ($errors->any())
                <div class="alert alert-danger">
                    <strong>Whoops!</strong> Terdapat beberapa masalah dengan input Anda.<br><br>
                    <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            
            {{-- Form disesuaikan untuk Staff --}}
            <form action="{{ route('staffs.update', $staff->id) }}" method="POST">
                @csrf
                @method('PUT')
                
                <h6 class="font-weight-bold text-primary">Data Diri Staff</h6>
                
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label for="name">Nama Lengkap</label>
                        <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $staff->name) }}" required>
                    </div>
                    <div class="col-md-6 form-group">
                        <label for="position">Posisi</label>
                        <input type="text" name="position" id="position" class="form-control" value="{{ old('position', $staff->position) }}" required>
                    </div>
                </div>

                <div class="row">
                     <div class="col-md-6 form-group">
                        <label for="phone_number">No. Telepon</label>
                        <input type="text" name="phone_number" id="phone_number" class="form-control" value="{{ old('phone_number', $staff->phone_number) }}">
                    </div>
                     <div class="col-md-6 form-group">
                        <label for="join_date">Tanggal Bergabung</label>
                        {{-- Perbaikan untuk format tanggal --}}
                        <input type="date" name="join_date" id="join_date" class="form-control" value="{{ old('join_date', $staff->join_date ? \Carbon\Carbon::parse($staff->join_date)->format('Y-m-d') : '') }}" required>
                     </div>
                </div>

                <hr>

                <h6 class="font-weight-bold text-primary">Kartu & Aturan Akses</h6>
                
                <div class="form-group">
                    <label for="master_card_id">Pilih Kartu RFID (Opsional)</label>
                    <select name="master_card_id" id="master_card_id" class="form-control">
                        <option value="">-- Tanpa Kartu --</option>
                        @forelse($availableCards as $card)
                            <option value="{{ $card->id }}" {{ old('master_card_id', $staff->master_card_id) == $card->id ? 'selected' : '' }}>
                                {{ $card->cardno }}
                                @if($card->id == $staff->master_card_id) (Kartu Saat Ini) @endif
                            </option>
                        @empty
                            {{-- Tetap tampilkan kartu saat ini meskipun tidak ada pilihan lain --}}
                             @if($staff->masterCard)
                                <option value="{{ $staff->master_card_id }}" selected>{{ $staff->masterCard->cardno }} (Kartu Saat Ini)</option>
                             @endif
                        @endforelse
                    </select>
                </div>
                
                <div class="form-group">
                    <div class="btn-group btn-group-toggle d-block" data-toggle="buttons">
                        <label class="btn btn-outline-primary {{ old('rule_type', $staff->rule_type ?? 'template') == 'template' ? 'active' : '' }}">
                            <input type="radio" name="rule_type" value="template" {{ old('rule_type', $staff->rule_type ?? 'template') == 'template' ? 'checked' : '' }}> Gunakan Template Aturan
                        </label>
                        <label class="btn btn-outline-secondary {{ old('rule_type', $staff->rule_type) == 'custom' ? 'active' : '' }}">
                            <input type="radio" name="rule_type" value="custom" {{ old('rule_type', $staff->rule_type) == 'custom' ? 'checked' : '' }}> Aturan Custom Manual
                        </label>
                    </div>
                </div>

                <div id="form_template_rule">
                    <div class="form-group">
                        <label>Pilih Template Aturan</label>
                        <select name="access_rule_id" class="form-control">
                            <option value="">-- Akses Default (Tanpa Batasan) --</option>
                            @foreach($accessRules as $rule)
                                <option value="{{ $rule->id }}" {{ old('access_rule_id', $staff->access_rule_id) == $rule->id ? 'selected' : '' }}>
                                    {{ $rule->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div id="form_custom_rule">
                    <p class="text-muted small">Isi kolom di bawah untuk membuat aturan khusus hanya untuk staff ini.</p>
                    <div class="row">
                        <div class="col-md-6 form-group"><label>Maksimal Tap per Hari</label><input type="number" name="max_taps_per_day" class="form-control" value="{{ old('max_taps_per_day', $staff->max_taps_per_day) }}" min="0"></div>
                        <div class="col-md-6 form-group"><label>Maksimal Tap per Bulan</label><input type="number" name="max_taps_per_month" class="form-control" value="{{ old('max_taps_per_month', $staff->max_taps_per_month) }}" min="0"></div>
                    </div>
                    <div class="form-group">
                        <label>Hari yang Diizinkan</label>
                        <div class="d-flex flex-wrap">
                            @php $selectedDays = old('allowed_days', $staff->allowed_days ?? []); @endphp
                            @foreach(['monday','tuesday','wednesday','thursday','friday','saturday','sunday'] as $day)
                            <div class="form-check form-check-inline mr-3">
                                <input class="form-check-input" type="checkbox" name="allowed_days[]" value="{{ $day }}" id="day_{{ $day }}" {{ in_array($day, $selectedDays) ? 'checked' : '' }}>
                                <label class="form-check-label text-capitalize" for="day_{{ $day }}">{{ $day }}</label>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Jam Mulai</label>
                            <input type="time" name="start_time" class="form-control" value="{{ old('start_time', $staff->start_time ? \Carbon\Carbon::parse($staff->start_time)->format('H:i') : '') }}">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Jam Selesai</label>
                            <input type="time" name="end_time" class="form-control" value="{{ old('end_time', $staff->end_time ? \Carbon\Carbon::parse($staff->end_time)->format('H:i') : '') }}">
                        </div>
                    </div>
                </div>
                
                <hr>
                <button class="btn btn-primary" type="submit">Update Staff</button>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    // Script untuk toggle form aturan
    $(document).ready(function() {
        function toggleRuleForms(type) {
            if (type === 'template') {
                $('#form_template_rule').show();
                $('#form_custom_rule').hide();
            } else { // type === 'custom'
                $('#form_template_rule').hide();
                $('#form_custom_rule').show();
            }
        }
        var initialType = $('input[name="rule_type"]:checked').val();
        toggleRuleForms(initialType);

        $('input[name="rule_type"]').change(function() {
            toggleRuleForms($(this).val());
        });
    });
</script>
@endpush
