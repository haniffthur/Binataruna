@extends('layouts.app')
@section('title', 'Tambah Member Baru')
@section('content')
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">@yield('title')</h1>
        <a href="{{ route('members.index') }}" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left fa-sm"></i>
            Kembali</a>
    </div>
    <div class="card shadow mb-4">
        <div class="card-body">
            @if ($errors->any())
                <div class="alert alert-danger">
                    <strong>Whoops!</strong> Terdapat beberapa masalah dengan input Anda.<br><br>
                    <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            <form action="{{ route('members.store') }}" method="POST" enctype="multipart/form-data">

                @csrf
                <h6 class="font-weight-bold text-primary">Data Diri Member</h6>
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label for="name">Nama Lengkap</label>
                        <input type="text" name="name" id="name" class="form-control" value="{{ old('name') }}" required>
                    </div>
                    <div class="col-md-6 form-group">
                        <label for="nickname">Nama Panggilan (Opsional)</label>
                        <input type="text" name="nickname" id="nickname" class="form-control" value="{{ old('nickname') }}">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label for="nis">NIS (Opsional)</label>
                        <input type="text" name="nis" id="nis" class="form-control" value="{{ old('nis') }}">
                    </div>
                    <div class="col-md-6 form-group">
                        <label for="nisnas">NISNAS (Opsional)</label>
                        <input type="text" name="nisnas" id="nisnas" class="form-control" value="{{ old('nisnas') }}">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 form-group">
                        <label for="photo">Foto Profil (Opsional)</label>
                        <input type="file" name="photo" id="photo" class="form-control-file" accept="image/*">
                    </div>
                    <div class="col-md-6 form-group">
                        <label for="phone_number">No. Telepon</label>
                        <input type="text" name="phone_number" id="phone_number" class="form-control" value="{{ old('phone_number') }}">
                    </div>
                </div>
                <div class="form-group">
                    <label for="address">Alamat</label>
                    <textarea name="address" id="address" class="form-control" rows="2">{{ old('address') }}</textarea>
                </div>
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label for="date_of_birth">Tanggal Lahir</label>
                        <input type="date" name="date_of_birth" id="date_of_birth" class="form-control" value="{{ old('date_of_birth') }}">
                    </div>
                    <div class="col-md-6 form-group">
                        <label for="parent_name">Nama Orang Tua</label>
                        <input type="text" name="parent_name" id="parent_name" class="form-control" value="{{ old('parent_name') }}">
                    </div>
                </div>
                <div class="form-group">
                    <label for="join_date">Tanggal Bergabung</label>
                    <input type="date" name="join_date" id="join_date" class="form-control" value="{{ old('join_date', date('Y-m-d')) }}" required>
                </div>
                <div class="form-group">
                    <label for="school_class_id">Kelas (Opsional)</label>
                    <select name="school_class_id" id="school_class_id" class="form-control">
                        <option value="">-- Tidak Masuk Kelas Apapun --</option>
                        @foreach($schoolClasses as $class)
                            <option value="{{ $class->id }}" {{ old('school_class_id') == $class->id ? 'selected' : '' }}>
                                {{ $class->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <hr>

                <h6 class="font-weight-bold text-primary">Kartu RFID</h6>
                <div class="form-group">
                    <label for="master_card_source">Sumber Kartu RFID</label>
                    <div class="btn-group btn-group-toggle d-block mb-3" data-toggle="buttons">
                        <label class="btn btn-outline-primary {{ old('master_card_source', 'select') == 'select' ? 'active' : '' }}">
                            <input type="radio" name="master_card_source" value="select" {{ old('master_card_source', 'select') == 'select' ? 'checked' : '' }}> Pilih dari yang Tersedia
                        </label>
                       
                    </div>

                    <div id="master_card_select_container" class="mb-3">
                        <label for="master_card_id">Pilih Kartu RFID</label>
                        <select name="master_card_id" id="master_card_id" class="form-control select2">
                            <option value="">-- Pilih Kartu yang Tersedia --</option>
                            @foreach($availableCards as $card)
                                <option value="{{ $card->id }}" {{ old('master_card_id') == $card->id ? 'selected' : '' }}>{{ $card->cardno }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div id="master_card_input_container" style="display:none;">
                        <label for="card_number_manual">Nomor Kartu RFID Manual</label>
                        <input type="text" name="card_number_manual" id="card_number_manual" class="form-control" value="{{ old('card_number_manual') }}" placeholder="Masukkan nomor kartu RFID">
                        <small class="form-text text-muted">Pastikan nomor kartu yang Anda masukkan belum terdaftar.</small>
                    </div>
                </div>
                <hr>

                <h6 class="font-weight-bold text-primary">Aturan Akses</h6>
                <div class="form-group">
                    <div class="btn-group btn-group-toggle d-block" data-toggle="buttons">
                        <label
                            class="btn btn-outline-primary {{ old('rule_type', 'template') == 'template' ? 'active' : '' }}"><input
                                type="radio" name="rule_type" value="template" {{ old('rule_type', 'template') == 'template' ? 'checked' : '' }}> Gunakan Template Aturan</label>
                        <label class="btn btn-outline-secondary {{ old('rule_type') == 'custom' ? 'active' : '' }}"><input
                                type="radio" name="rule_type" value="custom" {{ old('rule_type') == 'custom' ? 'checked' : '' }}> Aturan Custom Manual</label>
                    </div>
                </div>
            
                <div id="form_template_rule">
                    <div class="form-group">
                        <label>Pilih Template Aturan</label>
                        <select name="access_rule_id" class="form-control">
                            <option value="">-- Akses Default (Tanpa Batasan) --</option>
                            @foreach($accessRules as $rule)<option value="{{ $rule->id }}" {{ old('access_rule_id') == $rule->id ? 'selected' : '' }}>{{ $rule->name }}</option>@endforeach
                        </select>
                    </div>
                </div>

                <div id="form_custom_rule">
                    <p class="text-muted small">Isi kolom di bawah untuk membuat aturan khusus hanya untuk member ini.</p>
                    <div class="row">
                        <div class="col-md-6 form-group"><label>Maksimal Tap per Hari</label><input type="number"
                                name="max_taps_per_day" class="form-control" value="{{ old('max_taps_per_day') }}" min="0"
                                placeholder="Kosongkan jika tak terbatas"></div>
                        <div class="col-md-6 form-group"><label>Maksimal Tap per Bulan</label><input type="number"
                                name="max_taps_per_month" class="form-control" value="{{ old('max_taps_per_month') }}"
                                min="0" placeholder="Kosongkan jika tak terbatas"></div>
                    </div>
                    <div class="form-group">
                        <label>Hari yang Diizinkan (kosongkan jika semua hari boleh)</label>
                        <div class="d-flex flex-wrap">
                            @php $old_days = old('allowed_days', []); @endphp
                            @foreach(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day)
                                <div class="form-check form-check-inline mr-3">
                                    <input class="form-check-input" type="checkbox" name="allowed_days[]" value="{{ $day }}"
                                        id="day_{{ $day }}" {{ in_array($day, $old_days) ? 'checked' : '' }}>
                                    <label class="form-check-label text-capitalize" for="day_{{ $day }}">{{ $day }}</label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group"><label>Jam Mulai</label><input type="time" name="start_time"
                                class="form-control" value="{{ old('start_time') }}"></div>
                        <div class="col-md-6 form-group"><label>Jam Selesai</label><input type="time" name="end_time"
                                class="form-control" value="{{ old('end_time') }}"></div>
                    </div>
                </div>
                <hr>
                <button class="btn btn-primary" type="submit">Simpan Member</button>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    {{-- Include Select2 CSS and JS --}}
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        $(document).ready(function () {
            // Initialize Select2 for the RFID card selection
            $('#master_card_id').select2({
                placeholder: "-- Pilih Kartu yang Tersedia --",
                allowClear: true // This adds a clear button to the select
            });

            function toggleRuleForms(type) {
                if (type === 'template') {
                    $('#form_template_rule').show();
                    $('#form_custom_rule').hide();
                } else { // type === 'custom'
                    $('#form_template_rule').hide();
                    $('#form_custom_rule').show();
                }
            }

            function toggleMasterCardSource(source) {
                if (source === 'select') {
                    $('#master_card_select_container').show();
                    $('#master_card_input_container').hide();
                    $('#master_card_id').attr('required', true); // Make select required
                    $('#card_number_manual').removeAttr('required'); // Remove required from manual input
                } else { // source === 'input'
                    $('#master_card_select_container').hide();
                    $('#master_card_input_container').show();
                    $('#master_card_id').removeAttr('required'); // Remove required from select
                    $('#card_number_manual').attr('required', true); // Make manual input required
                }
            }

            // Initial state for access rules
            var initialRuleType = $('input[name="rule_type"]:checked').val();
            toggleRuleForms(initialRuleType);
            $('input[name="rule_type"]').change(function () {
                toggleRuleForms($(this).val());
            });

            // Initial state for master card source
            var initialCardSource = $('input[name="master_card_source"]:checked').val();
            toggleMasterCardSource(initialCardSource);
            $('input[name="master_card_source"]').change(function () {
                toggleMasterCardSource($(this).val());
            });
        });
    </script>
@endpush