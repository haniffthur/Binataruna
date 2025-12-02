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
                    <strong>Whoops!</strong> Terdapat masalah pada input Anda.<br>
                    <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <form action="{{ route('transactions.member.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                
                <div class="form-group row">
                    <label class="col-sm-2 col-form-label font-weight-bold">Tanggal Transaksi</label>
                    <div class="col-sm-4">
                        <input type="date" name="transaction_date" class="form-control" value="{{ old('transaction_date', date('Y-m-d')) }}" required>
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

                {{-- MEMBER LAMA --}}
                <div id="form_member_lama" style="{{ old('transaction_type', 'lama') == 'lama' ? '' : 'display: none;' }}">
                    
                    {{-- JENIS PEMBAYARAN --}}
                    <div class="form-group bg-light p-3 border rounded mb-3">
                        <label class="font-weight-bold mb-2">Jenis Pembayaran:</label>
                        <div class="custom-control custom-radio mb-2">
                            <input type="radio" id="payment_type_class" name="payment_type" class="custom-control-input" value="class" checked>
                            <label class="custom-control-label text-success font-weight-bold" for="payment_type_class">
                                <i class="fas fa-dumbbell mr-1"></i> Bayar Kelas / Latihan (Aktifkan Member)
                            </label>
                        </div>
                        <div class="custom-control custom-radio">
                            <input type="radio" id="payment_type_leave" name="payment_type" class="custom-control-input" value="leave">
                            <label class="custom-control-label text-danger font-weight-bold" for="payment_type_leave">
                                <i class="fas fa-pause-circle mr-1"></i> Bayar Cuti (Nonaktifkan Member)
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Pilih Member (Cari Nama)</label>
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

                {{-- MEMBER BARU --}}
                <div id="form_member_baru" style="{{ old('transaction_type') == 'baru' ? '' : 'display: none;' }}">
                    @include('members.partials.form-fields', ['member' => new \App\Models\Member])
                </div>
                <hr>

                <h6 class="font-weight-bold text-primary">Detail Pembayaran</h6>
                
                <div class="form-group">
                    <label>Pilih Produk / Kelas</label>
                    <select name="class_id" id="class_id" class="form-control" required>
                        <option value="" data-price="0" data-rule-id="">-- Pilih Kelas / Paket Cuti --</option>
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
                    <label>Biaya Pendaftaran (Member Baru)</label>
                    <input type="number" id="registration_fee" name="registration_fee" class="form-control" value="{{ old('registration_fee', 0) }}" min="0">
                </div>

                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>Total Tagihan (Bisa Diedit)</label>
                        <input type="number" name="custom_total_amount" id="total_price_input" class="form-control font-weight-bold" value="0">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Jumlah Bayar</label>
                        <input type="number" id="amount_paid" name="amount_paid" class="form-control" required min="0" value="{{ old('amount_paid') }}">
                    </div>
                </div>

                <div class="form-group">
                    <label>Keterangan / Catatan</label>
                    <textarea name="notes" id="notes" rows="2" class="form-control">{{ old('notes') }}</textarea>
                </div>

                <button type="submit" class="btn btn-success btn-lg btn-block mt-4"><i class="fas fa-save mr-2"></i>Proses Transaksi</button>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        // CACHE ELEMENTS
        const memberForm = $('#form_member_lama');
        const newMemberForm = $('#form_member_baru');
        const transactionTypeRadios = $('input[name="transaction_type"]');
        const paymentTypeRadios = $('input[name="payment_type"]');
        
        const memberSelect = $('#member_id');
        const detailSection = $('#member_details_section');
        const updateRuleSection = $('#update_access_rule_section');
        const updateRulesCheckbox = $('#update_rules_checkbox');
        const updateRulesForm = $('#update_rules_form');
        
        const classSelect = $('#class_id');
        const registrationFeeInput = $('#registration_fee');
        const registrationFeeContainer = $('#registration_fee_container');
        const totalPriceInput = $('#total_price_input');
        const ruleAutoMsg = $('#rule_auto_select_msg');
        
        const newMemberMasterCardSelect = $('#master_card_id_new'); 
        const newMemberAccessRuleSection = $('#access_rule_section_new_member'); 

        // 1. INIT SELECT2
        memberSelect.select2({
            theme: 'bootstrap4',
            placeholder: '-- Cari Nama Member --',
            allowClear: true,
            width: '100%'
        });

        // 2. TOGGLE MEMBER LAMA / BARU
        function toggleMemberForms(type) {
            if (type === 'lama') {
                memberForm.show(); newMemberForm.hide();
                memberSelect.prop('disabled', false).prop('required', true);
                newMemberForm.find(':input').prop('disabled', true);
                registrationFeeContainer.slideUp();
                registrationFeeInput.val(0); 
                togglePaymentType(paymentTypeRadios.filter(':checked').val()); 
            } else { 
                memberForm.hide(); detailSection.hide(); updateRuleSection.hide(); newMemberForm.show();
                memberSelect.prop('disabled', true).prop('required', false).val('').trigger('change');
                newMemberForm.find(':input').prop('disabled', false);
                registrationFeeContainer.slideDown();
                if(newMemberMasterCardSelect.length) newMemberMasterCardSelect.trigger('change');
            }
            calculatePrice();
        }

        // 3. TOGGLE PAYMENT TYPE (CLASS / CUTI)
        function togglePaymentType(type) {
            if (transactionTypeRadios.filter(':checked').val() === 'lama') {
                if (type === 'leave') {
                    updateRuleSection.hide(); 
                    ruleAutoMsg.addClass('d-none');
                    totalPriceInput.val(100000); 
                } else {
                    if (memberSelect.val()) updateRuleSection.show();
                    calculatePrice();
                }
                if (type === 'leave') updateRulesCheckbox.prop('checked', false).trigger('change');
            }
        }

        // 4. HITUNG HARGA
        function calculatePrice() {
            if (transactionTypeRadios.filter(':checked').val() === 'lama' && paymentTypeRadios.filter(':checked').val() === 'leave') return;

            const selectedOption = classSelect.find('option:selected');
            const classPrice = parseFloat(selectedOption.data('price')) || 0;
            const regFee = parseFloat(registrationFeeInput.val()) || 0;
            
            if (document.activeElement !== totalPriceInput[0]) {
                totalPriceInput.val(classPrice + regFee);
            }
        }

        // 5. AUTO-SELECT RULE
        function updateAccessRuleBasedOnClass() {
            if (transactionTypeRadios.filter(':checked').val() === 'lama' && paymentTypeRadios.filter(':checked').val() === 'leave') {
                ruleAutoMsg.addClass('d-none'); return;
            }

            const selectedOption = classSelect.find('option:selected');
            const ruleId = selectedOption.data('rule-id');
            const trxType = transactionTypeRadios.filter(':checked').val();

            if (ruleId) {
                ruleAutoMsg.removeClass('d-none');
                if (trxType === 'baru') {
                    const ruleRadio = newMemberForm.find('input[name="rule_type"][value="template"]');
                    const ruleSelect = newMemberForm.find('select[name="access_rule_id"]');
                    if(ruleRadio.length) { ruleRadio.prop('checked', true).trigger('change'); ruleSelect.val(ruleId).trigger('change'); }
                } else {
                    if (!updateRulesCheckbox.is(':checked')) updateRulesCheckbox.prop('checked', true).trigger('change');
                    setTimeout(() => {
                        const ruleRadio = updateRulesForm.find('input[name="update_rule_type"][value="template"]');
                        const ruleSelect = updateRulesForm.find('select[name="update_access_rule_id"]');
                        if(ruleRadio.length) { ruleRadio.parent().click(); ruleSelect.val(ruleId).trigger('change'); }
                    }, 300);
                }
            } else {
                ruleAutoMsg.addClass('d-none');
            }
        }

        // 6. POPULATE FORM
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

            const isTemplate = (data.rule_type === 'template' || !data.rule_type);
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

        // --- EVENT LISTENERS ---
        transactionTypeRadios.change(function() { toggleMemberForms($(this).val()); });
        paymentTypeRadios.change(function() { togglePaymentType($(this).val()); });

        classSelect.on('change', function() { calculatePrice(); updateAccessRuleBasedOnClass(); });
        registrationFeeInput.on('input', calculatePrice);
        totalPriceInput.on('input', calculatePrice);

        $(document).on('change', '#master_card_id_new', function() {
             const ruleSection = $(this).closest('form').find('#access_rule_section_new_member'); 
             if ($(this).val()) ruleSection.slideDown(); else ruleSection.slideUp();
        });

        // SELECT MEMBER LAMA
        memberSelect.on('select2:select', function (e) {
            const memberId = e.params.data.id;
            if (!memberId) return;

            detailSection.html('<div class="card-body">Memuat data...</div>').slideDown();
            if (paymentTypeRadios.filter(':checked').val() !== 'leave') updateRuleSection.show(); 
            updateRulesCheckbox.prop('checked', false);
            updateRulesForm.hide().empty();

            // Menggunakan Anti-Cache URL
            const url = `{{ url('/api/members') }}/${memberId}?t=${new Date().getTime()}`;

            fetch(url)
                .then(res => res.json())
                .then(data => {
                    // --- SAYA HAPUS BAGIAN BADGE STATUS DI SINI AGAR TIDAK BIKIN PUSING ---
                    detailSection.html(`
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
                        </div>
                    `);
                    
                    if (data.school_class_id) {
                        classSelect.off('change'); 
                        classSelect.val(data.school_class_id).trigger('change.select2'); 
                        calculatePrice(); 
                        classSelect.on('change', function() { calculatePrice(); updateAccessRuleBasedOnClass(); });
                    } else {
                        classSelect.val('').trigger('change');
                    }
                    updateRuleSection.data('rules', data);
                })
                .catch(err => {
                    console.error(err);
                    detailSection.html('<div class="card-body text-danger">Gagal memuat data.</div>');
                });
        });
        
        memberSelect.on('select2:clear', function () {
             detailSection.slideUp(); updateRulesCheckbox.prop('checked', false); updateRulesForm.hide().empty();
        });

        updateRulesCheckbox.change(function() {
            if ($(this).is(':checked')) populateAndShowUpdateForm(updateRuleSection.data('rules'));
            else updateRulesForm.slideUp().empty();
        });
        
        $(document).on('change', 'input[name="update_rule_type"]', function() {
            if($(this).val() === 'template') { $('#form_template_rule_update').show(); $('#form_custom_rule_update').hide(); }
            else { $('#form_template_rule_update').hide(); $('#form_custom_rule_update').show(); }
        });

        const initialType = transactionTypeRadios.filter(':checked').val();
        toggleMemberForms(initialType);
        togglePaymentType(paymentTypeRadios.filter(':checked').val());
    });
</script>
@endpush