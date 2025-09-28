@extends('layouts.app')

@section('title', 'Absensi Manual')

@section('content')
<div class="container-fluid">
    {{-- Bagian atas halaman (header, form, dll) tidak perlu diubah --}}
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Absensi Manual</h1>
        <a href="{{ route('dashboard') }}" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
        </a>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Form Absensi Manual</h6>
                </div>
                <div class="card-body">
                    <form id="manualAttendanceForm">
                        @csrf
                        <div class="form-group">
                            <label for="memberSearch">Cari Nama Member</label>
                            <div class="position-relative">
                                <input type="text" 
                                       class="form-control" 
                                       id="memberSearch" 
                                       placeholder="Ketik minimal 2 huruf..."
                                       autocomplete="off">
                                <div id="memberDropdown"></div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="memberCardNo">Nomor Kartu</label>
                            <input type="text" 
                                   class="form-control bg-light" 
                                   id="memberCardNo" 
                                   placeholder="Terisi otomatis" 
                                   readonly>
                        </div>
                        
                        <input type="hidden" id="memberId" name="member_id">
                        <input type="hidden" id="cardNo" name="cardno">

                        <div class="form-group mt-4">
                            <button type="submit" class="btn btn-primary btn-block" id="submitBtn" disabled>
                                <i class="fas fa-hand-pointer"></i> Submit Absen
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-info">Petunjuk Penggunaan</h6>
                </div>
                <div class="card-body">
                    <ol>
                        <li>Ketik nama member di kolom pencarian.</li>
                        <li>Pilih member dari daftar yang muncul.</li>
                        <li>Nomor kartu akan terisi secara otomatis.</li>
                        <li>Klik tombol "Submit Absen" untuk merekam kehadiran.</li>
                    </ol>
                </div>
            </div>

            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-success">Absen Manual Terbaru Hari Ini</h6>
                </div>
                <div class="card-body" id="recentAttendance" style="max-height: 300px; overflow-y: auto;">
                    <p class="text-muted">Loading...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
#memberDropdown {
    position: absolute; top: 100%; left: 0; right: 0; background: white;
    border: 1px solid #ddd; border-radius: 0 0 .35rem .35rem; max-height: 200px;
    overflow-y: auto; z-index: 1000; box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}
.member-option {
    padding: 10px 15px; cursor: pointer; border-bottom: 1px solid #eee;
}
.member-option:hover { background-color: #f8f9fa; }
.member-option:last-child { border-bottom: none; }
</style>
@endsection
@push('scripts')
<script>
$(document).ready(function() {
    let selectedMemberId = null, selectedCardNo = null;

    function clearSelection() {
        selectedMemberId = null; selectedCardNo = null;
        $('#memberId, #cardNo, #memberCardNo').val('');
        $('#submitBtn').prop('disabled', true);
    }
    
    $('#memberSearch').on('input', function() {
        const query = $(this).val();
        if (query.length < 2) {
            $('#memberDropdown').empty().hide();
            if (query.length === 0) clearSelection();
            return;
        }

        $('#memberDropdown').html('<div class="member-option text-muted">Mencari...</div>').show();
        
        $.ajax({
            url: '{{ route("manual.attendance.search") }}',
            method: 'GET', data: { q: query },
            success: function(response) {
                let dropdown = '';
                if (response.results && response.results.length > 0) {
                    response.results.forEach(function(member) {
                        dropdown += `<div class="member-option" data-id="${member.id}" data-cardno="${member.cardno}" data-name="${member.text}"><strong>${member.text}</strong></div>`;
                    });
                } else {
                    dropdown = '<div class="member-option text-muted">Tidak ada member ditemukan</div>';
                }
                $('#memberDropdown').html(dropdown).show();
            },
            error: function() { $('#memberDropdown').html('<div class="member-option text-danger">Error saat mencari data.</div>'); }
        });
    });
    
    $(document).on('click', '.member-option', function() {
        if (!$(this).data('id')) return;
        selectedMemberId = $(this).data('id'); selectedCardNo = $(this).data('cardno');
        $('#memberSearch').val($(this).data('name')); $('#memberCardNo').val(selectedCardNo);
        $('#memberId').val(selectedMemberId); $('#cardNo').val(selectedCardNo);
        $('#submitBtn').prop('disabled', false); $('#memberDropdown').empty().hide();
    });
    
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#memberSearch, #memberDropdown').length) $('#memberDropdown').hide();
    });
    
    $('#manualAttendanceForm').on('submit', function(e) {
        e.preventDefault();
        if (!selectedMemberId) return;

        const submitBtn = $('#submitBtn'), originalBtnHtml = submitBtn.html();
        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');
        
        $.ajax({
            url: '{{ route("manual.attendance.process") }}',
            method: 'POST', data: $(this).serialize(),
            success: function(response) {
                // --- AWAL PERUBAHAN ---
                const icon = response.success ? 'success' : 'error';
                const title = response.success ? 'Berhasil!' : 'Gagal!';
                const detail = response.member_name ? `<br><small>Member: ${response.member_name} | Waktu: ${response.time}</small>` : '';

                Swal.fire({
                    icon: icon,
                    title: title,
                    html: response.message + detail,
                    timer: 4000,
                    timerProgressBar: true,
                    showConfirmButton: false
                });
                
                // Hanya reset form jika absen berhasil
                if(response.success) {
                    $('#manualAttendanceForm')[0].reset(); 
                    clearSelection(); 
                    $('#memberSearch').focus(); 
                    loadRecentAttendance();
                }
                // --- AKHIR PERUBAHAN ---
            },
            error: function(xhr) {
                // --- AWAL PERUBAHAN ---
                const errorMsg = xhr.responseJSON?.message || 'Terjadi kesalahan sistem.';
                Swal.fire({
                    icon: 'error',
                    title: 'Oops... Terjadi Kesalahan!',
                    text: errorMsg
                });
                // --- AKHIR PERUBAHAN ---
            },
            complete: function() { 
                submitBtn.prop('disabled', false).html(originalBtnHtml); 
            }
        });
    });
    
    function loadRecentAttendance() {
        $.ajax({
            url: '{{ route("manual.attendance.recent") }}', method: 'GET',
            success: function(data) {
                let html = '';
                if (data.length > 0) {
                    data.forEach(function(log) {
                        const badgeClass = log.status == 1 ? 'success' : 'danger';
                        html += `<div class="small mb-2 pb-2 border-bottom"><strong>${log.member_name}</strong><span class="badge badge-${badgeClass} float-right">${log.message}</span><br><span class="text-muted">${log.time}</span></div>`;
                    });
                } else {
                    html = '<p class="text-muted">Belum ada absen manual hari ini.</p>';
                }
                $('#recentAttendance').html(html);
            },
            error: function() { $('#recentAttendance').html('<p class="text-danger">Gagal memuat data terbaru.</p>'); }
        });
    }
    
    loadRecentAttendance();
    $('#memberSearch').focus();
    setInterval(loadRecentAttendance, 30000);
});
</script>
@endpush