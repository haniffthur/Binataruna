<?php

use App\Http\Controllers\MemberController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TapValidationController;
use App\Http\Controllers\Api\TicketValidationController;
use App\Http\Controllers\Api\CardRegistrationController;
use App\Http\Controllers\Api\TappedCardController; // Tambahkan ini
use App\Http\Controllers\ManualAttendanceController;

    Route::get('/members/search', [ManualAttendanceController::class, 'searchMembers'])->name('api.members.search');

// Endpoint untuk validasi tap kartu RFID dari ESP8266
Route::get('/cardno', [TapValidationController::class, 'validateTap']);

Route::post('/cards/tapped-uid', [TappedCardController::class, 'storeTappedUid']);

// Endpoint BARU untuk halaman web agar bisa mengambil UID yang baru di-tap
Route::get('/cards/get-tapped-uid', [TappedCardController::class, 'getTappedUid']);

Route::post('/cards/register', [CardRegistrationController::class, 'register']);

// Endpoint untuk validasi tiket QR Code dari pemindai
Route::get('/ticket', [TicketValidationController::class, 'validateTicket']);

Route::get('/manual-attendance/search', [ManualAttendanceController::class, 'searchMembers'])
     ->name('manual.attendance.search');

Route::post('/manual-attendance', [ManualAttendanceController::class, 'processAttendance'])
      ->name('manual.attendance.process');

Route::get('/manual-attendance/recent', [ManualAttendanceController::class, 'recent'])
     ->name('manual.attendance.recent');

     Route::get('/reports/search-members', [MemberController::class, 'searchMembers'])->name('reports.members.search');

// Rute default dari file Anda
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
