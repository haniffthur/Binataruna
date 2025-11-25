@extends('layouts.app')
@section('title', 'Transaksi Kelas Member')

@push('styles')
<style>
    .member-details-card {
        background-color: #f8f9fc;
        border-left: 4px solid #4e73df;
    }
    #update_rules_form {
        border-left: 4px solid #f6c23e;
    }
    /* Fix agar Select2 terlihat rapi */
    .select2-container--bootstrap4 .select2-selection--single {
        height: calc(1.5em + .75rem + 2px) !important;
    }
    .select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered {
        line-height: calc(1.5em + .75rem) !important;
        padding-left: 0.75rem;
    }
    .select2-container--bootstrap4 .select2-selection--single .select2-selection__arrow {
        height: calc(1.5em + .75rem) !important;
    }
</style>
@endpush

@section('content')
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">@yield('title')</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header"><h6 class="m-0 font-weight-bold text-primary">Form Transaksi Member</h6></div>
        <div class="card-body">
            @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
            @if ($errors->any())
                <div class="alert alert-danger">
                    <strong>Whoops!</strong> Terdapat beberapa masalah dengan input Anda.<br><br>
                    <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <form action="{{ route('transactions.member.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                
                {{-- 1. TANGGAL TRANSAKSI --}}
                <div class="form-group row">
                    <label for="transaction_date" class="col-sm-2 col-form-label font-weight-bold">Tanggal Transaksi</label>
                    <div class="col-sm-4">
                        <input type="date" name="transaction_date" id="transaction_date" class="form-control" value="{{ old('transaction_date', date('Y-m-d')) }}" required>
                    </div>
                </div>
                <hr>

                <div class="form-group">
                    <label>Tipe Transaksi</label>
                    <div class="btn-group btn-group-toggle d-block" data-toggle="buttons">
                        <label class="btn btn-outline-primary {{ old('transaction_type', 'lama') == 'lama' ? 'active' : '' }}">
                            <input type="radio" name="transaction_type" value="lama" {{ old('transaction_type', 'lama') == 'lama' ? 'checked' : '' }}> Member Lama
                        </label>
                        <label class="btn btn-outline-secondary {{ old('transaction_type') == 'baru' ? 'active' : '' }}">
                            <input type="radio" name="transaction_type" value="baru" {{ old('transaction_type') == 'baru' ? 'checked' : '' }}> Daftarkan Member Baru
                        </label>
                    </div>
                </div>

                {{-- Opsi untuk Member Lama --}}
                <div id="form_member_lama" style="{{ old('transaction_type', 'lama') == 'lama' ? '' : 'display: none;' }}">
                    <div class="form-group">
                        <label for="member_id">Pilih Member (Cari Nama)</label>
                        <select name="member_id" id="member_id" class="form-control" style="width: 100%;">
                            <option value="">-- Cari Nama Member --</option>
                            @foreach($members as $member)
                                <option value="{{ $member->id }}" {{ old('member_id') == $member->id ? 'selected' : '' }}>{{ $member->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div id="member_details_section" class="card member-details-card mb-3" style="display: none;"></div>

                    <div id="update_access_rule_section" style="display: none;">
                        <hr>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="update_rules" value="1" id="update_rules_checkbox">
                            <label class="form-check-label font-weight-bold text-primary" for="update_rules_checkbox">
                                Ubah Aturan Akses Member Ini?
                            </label>
                        </div>
                        <div id="update_rules_form" class="p-3 border rounded bg-white" style="display: none;"></div>
                    </div>
                </div>

                {{-- Form untuk Member Baru --}}
                <div id="form_member_baru" style="{{ old('transaction_type') == 'baru' ? '' : 'display: none;' }}">
                    @include('members.partials.form-fields', ['member' => new \App\Models\Member])
                </div>
                <hr>

                {{-- Bagian Transaksi Inti --}}
                <h6 class="font-weight-bold text-primary">Detail Pembayaran</h6>
                
                <div class="form-group">
                    <label for="class_id">Pilih Kelas</label>
                    <select name="class_id" id="class_id" class="form-control" required>
                        <option value="" data-price="0" data-rule-id="">-- Pilih Kelas --</option>
                        @foreach($schoolClasses as $class)
                            <option value="{{ $class->id }}" 
                                    data-price="{{ $class->price }}" 
                                    data-rule-id="{{ $class->access_rule_id ?? '' }}"
                                    {{ old('class_id') == $class->id ? 'selected' : '' }}>
                                {{ $class->name }} - Rp {{ number_format($class->price) }}
                            </option>
                        @endforeach
                    </select>
                    <small class="text-muted font-italic d-none" id="rule_auto_select_msg">
                        * Aturan akses otomatis disesuaikan dengan kelas yang dipilih.
                    </small>
                </div>

                <div class="form-group" id="registration_fee_container" style="display: none;">
                    <label for="registration_fee">Biaya Pendaftaran (Member Baru)</label>
                    <input type="number" id="registration_fee" name="registration_fee" class="form-control" value="{{ old('registration_fee', 0) }}" min="0">
                    <small class="text-muted">Biaya administrasi awal.</small>
                </div>

                {{-- UPDATE: Hilangkan Kolom Kembalian --}}
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>Total Tagihan (Bisa Diedit)</label>
                        <input type="number" name="custom_total_amount" id="total_price_input" class="form-control font-weight-bold" value="0">
                    </div>
                    <div class="col-md-6 form-group">
                        <label for="amount_paid">Jumlah Bayar</label>
                        <input type="number" id="amount_paid" name="amount_paid" class="form-control" required min="0" value="{{ old('amount_paid') }}">
                    </div>
                </div>

                <div class="form-group">
                    <label for="notes">Keterangan / Catatan (Opsional)</label>
                    <textarea name="notes" id="notes" rows="2" class="form-control" placeholder="Contoh: Lunas, Cicilan 1, Potongan Diskon, dll.">{{ old('notes') }}</textarea>
                </div>

                <button type="submit" class="btn btn-success btn-lg btn-block mt-4"><i class="fas fa-save mr-2"></i>Proses Transaksi</button>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        // --- CACHE ELEMEN ---
        const memberForm = $('#form_member_lama');
        const newMemberForm = $('#form_member_baru');
        const transactionTypeRadios = $('input[name="transaction_type"]');
        const memberSelect = $('#member_id');
        const detailSection = $('#member_details_section');
        const updateRuleSection = $('#update_access_rule_section');
        const updateRulesCheckbox = $('#update_rules_checkbox');
        const updateRulesForm = $('#update_rules_form');
        
        const classSelect = $('#class_id');
        const registrationFeeInput = $('#registration_fee');
        const registrationFeeContainer = $('#registration_fee_container');
        const totalPriceInput = $('#total_price_input');
        const amountPaidInput = $('#amount_paid');
        // const changeDisplay = $('#change_display'); // HAPUS INI
        const ruleAutoMsg = $('#rule_auto_select_msg');
        
        const newMemberMasterCardSelect = $('#master_card_id_new'); 
        const newMemberAccessRuleSection = $('#access_rule_section_new_member'); 

        // 1. INISIALISASI SELECT2
        memberSelect.select2({
            theme: 'bootstrap4',
            placeholder: '-- Cari Nama Member --',
            allowClear: true,
            width: '100%'
        });

        // 2. FUNGSI TOGGLE FORM
        function toggleMemberForms(type) {
            if (type === 'lama') {
                memberForm.show();
                newMemberForm.hide();
                memberSelect.prop('disabled', false).prop('required', true);
                newMemberForm.find(':input').prop('disabled', true);
                registrationFeeContainer.slideUp();
                registrationFeeInput.val(0); 
            } else { 
                memberForm.hide();
                detailSection.hide();
                updateRuleSection.hide();
                newMemberForm.show();
                memberSelect.prop('disabled', true).prop('required', false).val('').trigger('change');
                newMemberForm.find(':input').prop('disabled', false);
                registrationFeeContainer.slideDown();
                
                if(newMemberMasterCardSelect.length) newMemberMasterCardSelect.trigger('change');
            }
            calculatePrice();
        }

        // 3. FUNGSI HITUNG HARGA (TANPA KEMBALIAN)
        function calculatePrice() {
            const selectedOption = classSelect.find('option:selected');
            const classPrice = parseFloat(selectedOption.data('price')) || 0;
            const regFee = parseFloat(registrationFeeInput.val()) || 0;
            
            const calculatedTotal = classPrice + regFee;
            
            if (document.activeElement !== totalPriceInput[0]) {
                totalPriceInput.val(calculatedTotal);
            }

            // HAPUS LOGIKA KEMBALIAN
        }

        // 4. FUNGSI POPULATE FORM UPDATE RULES
        function populateAndShowUpdateForm(data = {}) {
             data = data || {}; 
             const accessRulesData = {!! json_encode($accessRules) !!};
             let templateOptions = `<option value="">-- Akses Default --</option>`;
             
             accessRulesData.forEach(rule => {
                const isSelected = data.access_rule_id == rule.id ? 'selected' : '';
                templateOptions += `<option value="${rule.id}" ${isSelected}>${rule.name}</option>`;
            });
            
            const days = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
            let daysCheckboxes = '';
            const currentDays = data.allowed_days || []; 
            
            days.forEach(day => {
                const isChecked = currentDays.includes(day) ? 'checked' : '';
                daysCheckboxes += `<div class="form-check form-check-inline mr-3"><input class="form-check-input" type="checkbox" name="update_allowed_days[]" value="${day}" ${isChecked}><label class="form-check-label text-capitalize">${day}</label></div>`;
            });

            const isTemplate = (data.rule_type === 'template' || !data.rule_type) ? true : false;
            const tplChecked = isTemplate ? 'checked' : '';
            const cstChecked = !isTemplate ? 'checked' : '';
            const activeTpl = isTemplate ? 'active' : '';
            const activeCst = !isTemplate ? 'active' : '';

            const formHtml = `
                <div class="form-group">
                    <div class="btn-group btn-group-toggle d-block" data-toggle="buttons">
                        <label class="btn btn-outline-primary ${activeTpl}"><input type="radio" name="update_rule_type" value="template" ${tplChecked}> Template</label>
                        <label class="btn btn-outline-secondary ${activeCst}"><input type="radio" name="update_rule_type" value="custom" ${cstChecked}> Custom</label>
                    </div>
                </div>
                <div id="form_template_rule_update" style="${isTemplate ? '' : 'display:none;'}">
                    <div class="form-group"><label>Pilih Template</label><select name="update_access_rule_id" class="form-control">${templateOptions}</select></div>
                </div>
                <div id="form_custom_rule_update" style="${!isTemplate ? '' : 'display:none;'}">
                    <div class="row"><div class="col-md-6 form-group"><label>Maks. Tap/Hari</label><input type="number" name="update_max_taps_per_day" class="form-control" value="${data.max_taps_per_day || ''}" min="0"></div><div class="col-md-6 form-group"><label>Maks. Tap/Bulan</label><input type="number" name="update_max_taps_per_month" class="form-control" value="${data.max_taps_per_month || ''}" min="0"></div></div>
                    <div class="form-group"><label>Hari</label><div class="d-flex flex-wrap">${daysCheckboxes}</div></div>
                    <div class="row"><div class="col-md-6 form-group"><label>Jam Mulai</label><input type="time" name="update_start_time" class="form-control" value="${data.start_time || ''}"></div><div class="col-md-6 form-group"><label>Jam Selesai</label><input type="time" name="update_end_time" class="form-control" value="${data.end_time || ''}"></div></div>
                </div>`;
            
            updateRulesForm.html(formHtml).slideDown();
        }

        // 5. FUNGSI AUTO-SELECT RULE
        function updateAccessRuleBasedOnClass() {
            const selectedOption = classSelect.find('option:selected');
            const ruleId = selectedOption.data('rule-id');
            const trxType = transactionTypeRadios.filter(':checked').val();

            if (ruleId) {
                ruleAutoMsg.removeClass('d-none');
                
                if (trxType === 'baru') {
                    const ruleRadio = newMemberForm.find('input[name="rule_type"][value="template"]');
                    const ruleSelect = newMemberForm.find('select[name="access_rule_id"]');
                    if(ruleRadio.length) {
                        ruleRadio.prop('checked', true).trigger('change');
                        ruleSelect.val(ruleId).trigger('change');
                    }
                } else {
                    if (!updateRulesCheckbox.is(':checked')) {
                        updateRulesCheckbox.prop('checked', true).trigger('change');
                    }
                    setTimeout(() => {
                        const ruleRadio = updateRulesForm.find('input[name="update_rule_type"][value="template"]');
                        const ruleSelect = updateRulesForm.find('select[name="update_access_rule_id"]');
                        if(ruleRadio.length) {
                            ruleRadio.parent().click(); 
                            ruleSelect.val(ruleId).trigger('change');
                        }
                    }, 300);
                }
            } else {
                ruleAutoMsg.addClass('d-none');
            }
        }
        
        // --- EVENT LISTENERS ---
        
        transactionTypeRadios.change(function() { toggleMemberForms($(this).val()); });

        classSelect.on('change', function() {
            calculatePrice();
            updateAccessRuleBasedOnClass();
        });

        registrationFeeInput.on('input', calculatePrice);
        totalPriceInput.on('input', calculatePrice);
        amountPaidInput.on('input', calculatePrice);

        $(document).on('change', '#master_card_id_new', function() {
             const ruleSection = $(this).closest('form').find('#access_rule_section_new_member'); 
             if ($(this).val()) {
                 if(ruleSection.length) ruleSection.slideDown();
             } else {
                 if(ruleSection.length) ruleSection.slideUp();
             }
        });

        memberSelect.on('select2:select', function (e) {
            const memberId = e.params.data.id;
            if (!memberId) return;

            detailSection.html('<div class="card-body">Memuat data...</div>').slideDown();
            updateRuleSection.show(); 
            updateRulesCheckbox.prop('checked', false);
            updateRulesForm.hide().empty();

            const url = `{{ url('/api/members') }}/${memberId}`;

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    const memberDetailsHtml = `
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 text-center align-self-center">
                                    <img src="${data.photo_url}" class="img-fluid rounded" style="max-height: 120px;">
                                </div>
                                <div class="col-md-9">
                                    <h5 class="font-weight-bold text-dark">${data.name}</h5>
                                    <p class="mb-1"><strong>Kelas Saat Ini:</strong> <span class="badge badge-secondary">${data.class_name}</span></p>
                                    <p class="mb-1"><strong>Kartu RFID:</strong> <span class="badge badge-info">${data.card_uid}</span></p>
                                    <p class="mb-0"><strong>Aturan Akses:</strong> <span>${data.access_rule}</span></p>
                                </div>
                            </div>
                        </div>`;
                    
                    detailSection.html(memberDetailsHtml);
                    
                    if (data.school_class_id) {
                        classSelect.val(data.school_class_id).trigger('change');
                    } else {
                        classSelect.val('').trigger('change');
                    }
                    
                    updateRuleSection.data('rules', data);
                });
        });
        
        memberSelect.on('select2:clear', function (e) {
             detailSection.slideUp();
             updateRulesCheckbox.prop('checked', false);
             updateRulesForm.hide().empty();
        });

        updateRulesCheckbox.change(function() {
            if ($(this).is(':checked')) {
                const data = updateRuleSection.data('rules') || {};
                populateAndShowUpdateForm(data);
            } else {
                updateRulesForm.slideUp().empty();
            }
        });
        
        $(document).on('change', 'input[name="update_rule_type"]', function() {
            if($(this).val() === 'template') {
                $('#form_template_rule_update').show();
                $('#form_custom_rule_update').hide();
            } else {
                $('#form_template_rule_update').hide();
                $('#form_custom_rule_update').show();
            }
        });

        const initialType = transactionTypeRadios.filter(':checked').val();
        toggleMemberForms(initialType);
        calculatePrice();
    });
</script>
@endpush