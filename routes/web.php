<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Dashboard;
use App\Http\Controllers\UserController;
use App\Http\Controllers\MasterCardController;
use App\Http\Controllers\AccessRuleController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\CoachController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\SchoolClassController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TaplogsController;
use App\Http\Controllers\TicketScanLogController;
use App\Http\Controllers\Api\MemberDetailController;
use App\Http\Controllers\ManualAttendanceController;
use App\Http\Controllers\ProfileController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Rute Publik (Tidak perlu login)
Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
// Disarankan menggunakan POST untuk logout demi keamanan (mencegah CSRF)
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');


// --- GRUP UNTUK SEMUA HALAMAN YANG MEMERLUKAN LOGIN ---
Route::middleware(['auth'])->group(function () {

    // == DASHBOARD ==
    Route::get('/dashboard', [Dashboard::class, 'index'])->name('dashboard');
    Route::get('/dashboard/report', [Dashboard::class, 'generateReport'])->name('dashboard.report');
    Route::get('/api/dashboard/chart-data', [Dashboard::class, 'getChartData'])->name('api.dashboard.chart-data');

    // == PENGELOLAAN PENGGUNA & AKSES ==
    Route::resource('users', UserController::class);
    Route::resource('access-rules', AccessRuleController::class);


     // RUTE EDIT PROFILE
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // == MASTER DATA ==
    Route::resource('master-cards', MasterCardController::class);
    Route::resource('classes', SchoolClassController::class);
    Route::resource('tickets', TicketController::class);

    // == MANAJEMEN MEMBER (Dikelompokkan) ==
    Route::prefix('members')->name('members.')->group(function () {
        Route::get('/download-template', [MemberController::class, 'downloadTemplate'])->name('download.template');
        Route::post('/import', [MemberController::class, 'import'])->name('import');
        Route::get('/export-report', [MemberController::class, 'exportReport'])->name('export.report');
        Route::get('/{member}/download-photo', [MemberController::class, 'downloadPhoto'])->name('download.photo');
        Route::post('/{member}/toggle-status', [MemberController::class, 'toggleStatus'])->name('toggle-status');
        Route::get('/{member}/export-log', [MemberController::class, 'exportLog'])->name('export-log');
        Route::get('/payment-status-report', [MemberController::class, 'paymentStatusReportForm'])->name('payment-status-report');
        Route::get('/payment-status-report/export', [MemberController::class, 'exportPaymentStatusReport'])->name('export-payment-status-report');
      
        
        // --- Laporan Absensi ---
        Route::get('/attendance-report', [MemberController::class, 'attendanceReportForm'])->name('attendance-report');
        // NAMA RUTE DIPERBAIKI AGAR SESUAI DENGAN YANG DIPANGGIL DI BLADE
        Route::get('/attendance-report/export', [MemberController::class, 'exportAttendanceReport'])->name('export-attendance-report');
    });
    Route::resource('members', MemberController::class); // Tetap di luar prefix agar URL standarnya (/members, /members/create) tidak berubah

    // == MANAJEMEN COACH & STAFF ==
    Route::resource('coaches', CoachController::class);
    Route::resource('staffs', StaffController::class);

    // == TRANSAKSI (Dikelompokkan dan dilindungi Auth) ==
    Route::prefix('transactions')->name('transactions.')->group(function () {
        Route::get('/', [TransactionController::class, 'index'])->name('index');
        Route::get('/export-excel', [TransactionController::class, 'exportExcel'])->name('export.excel');
        
        // Transaksi Member
        Route::get('/member/create', [TransactionController::class, 'createMemberTransaction'])->name('member.create');
        Route::post('/member', [TransactionController::class, 'storeMemberTransaction'])->name('member.store');
        
        // Transaksi Non-Member
        Route::get('/non-member/create', [TransactionController::class, 'createNonMemberTransaction'])->name('non-member.create');
        Route::post('/non-member', [TransactionController::class, 'storeNonMemberTransaction'])->name('non-member.store');
        Route::get('/non-member-receipt/{id}', [TransactionController::class, 'showNonMemberReceipt'])->name('non-member-receipt.show');
        Route::get('/non-member-transactions/{id}', [TransactionController::class, 'showNonMemberDetail'])->name('non-member-transactions.show');
    });

    // == LOGS ==
    Route::get('/tap-logs', [TaplogsController::class, 'index'])->name('tap-logs.index');
    Route::get('/api/tap-logs/latest', [TapLogsController::class, 'fetchLatest'])->name('api.tap-logs.latest');
    Route::get('/tap-logs/export-excel', [TaplogsController::class, 'exportExcel'])->name('tap-logs.export.excel');
    Route::get('/ticket-scan-logs', [TicketScanLogController::class, 'index'])->name('ticket-scan-logs.index');

    // == ABSENSI MANUAL (Dikelompokkan) ==
    Route::prefix('manual-attendance')->name('manual.attendance.')->group(function () {
        Route::get('/', [ManualAttendanceController::class, 'index'])->name('index');
        Route::post('/', [ManualAttendanceController::class, 'processAttendance'])->name('process');
        Route::get('/recent', [ManualAttendanceController::class, 'recent'])->name('recent');
        // NAMA RUTE DIPERBAIKI AGAR SESUAI DENGAN YANG DIPANGGIL DI JAVASCRIPT
        Route::get('/search', [ManualAttendanceController::class, 'searchMembers'])->name('search');
    });

    // == API (Internal) ==
    // Rute API sebaiknya dipisah ke routes/api.php, tapi untuk saat ini kita kelompokkan di sini
    Route::prefix('api')->name('api.')->group(function() {
        Route::get('/members/{member}', [MemberDetailController::class, 'show'])->name('members.show');
        Route::get('/tap-logs/latest', [TaplogsController::class, 'fetchLatest'])->name('tap-logs.latest');
        Route::get('/ticket-scan-logs/latest', [TicketScanLogController::class, 'fetchLatest'])->name('ticket-scan-logs.latest');
    });

    Route::get('/manual-attendance/search', [ManualAttendanceController::class, 'searchMembers'])
     ->name('manual.attendance.search');

});