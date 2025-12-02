<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\SchoolClass;
use App\Models\Ticket;
use App\Models\MasterCard;
use App\Models\AccessRule;
use App\Models\MemberTransaction;
use App\Models\NonMemberTransaction;
use App\Models\NonMemberTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use App\Exports\TransactionsExport;
use Maatwebsite\Excel\Facades\Excel;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'period' => 'nullable|in:today,this_week,this_month,custom',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'class_id' => 'nullable|integer|exists:classes,id',
            'type' => 'nullable|in:all,member,non-member',
        ]);

        $filterPeriod = $request->input('period', 'all_time');
        $start = null;
        $end = null;

        switch ($filterPeriod) {
            case 'today':
                $start = now()->startOfDay(); $end = now()->endOfDay(); break;
            case 'this_week':
                $start = now()->startOfWeek(); $end = now()->endOfWeek(); break;
            case 'this_month':
                $start = now()->startOfMonth(); $end = now()->endOfMonth(); break;
            case 'custom':
                if ($request->start_date && $request->end_date) {
                    $start = Carbon::parse($request->start_date)->startOfDay();
                    $end = Carbon::parse($request->end_date)->endOfDay();
                }
                break;
        }

        $memberQuery = DB::table('member_transactions')
            ->join('members', 'member_transactions.member_id', '=', 'members.id')
            ->leftJoin('transaction_details', 'member_transactions.id', '=', 'transaction_details.detailable_id')
            ->leftJoin('classes', 'transaction_details.purchasable_id', '=', 'classes.id')
            ->where('transaction_details.detailable_type', 'App\\Models\\MemberTransaction')
            ->select(
                'member_transactions.id',
                'members.name as customer_name',
                'member_transactions.total_amount',
                'member_transactions.transaction_date',
                DB::raw("'Member' as transaction_type"),
                'classes.name as item_name',
                'classes.id as class_id'
            );

        $nonMemberQuery = DB::table('non_member_transactions')
            ->select(
                'id',
                'customer_name',
                'total_amount',
                'transaction_date',
                DB::raw("'Non-Member' as transaction_type"),
                DB::raw("'(Transaksi Tiket)' as item_name"),
                DB::raw("NULL as class_id")
            );

        if ($request->filled('name')) {
            $memberQuery->where('members.name', 'like', '%' . $request->name . '%');
            $nonMemberQuery->where('non_member_transactions.customer_name', 'like', '%' . $request->name . '%');
        }
        if ($start && $end) {
            $memberQuery->whereBetween('member_transactions.transaction_date', [$start, $end]);
            $nonMemberQuery->whereBetween('non_member_transactions.transaction_date', [$start, $end]);
        }
        if ($request->filled('class_id')) {
            $memberQuery->where('classes.id', $request->class_id);
            $nonMemberQuery->whereRaw('1 = 0'); 
        }

        $type = $request->input('type', 'all');
        $unionQuery = $memberQuery->unionAll($nonMemberQuery);
        $finalQuery = DB::query()->fromSub($unionQuery, 'transactions');

        if ($type === 'member') $finalQuery->where('transaction_type', 'Member');
        elseif ($type === 'non-member') $finalQuery->where('transaction_type', 'Non-Member');

        $transactions = $finalQuery->orderBy('transaction_date', 'desc')->paginate(20)->withQueryString();
        $schoolClasses = SchoolClass::orderBy('name')->get();

        return view('transactions.index', compact('transactions', 'schoolClasses'));
    }

    // ========================================================================
    // FITUR BARU: TRANSAKSI MEMBER (Create & Store)
    // ========================================================================

    public function createMemberTransaction()
    {
        $members = Member::orderBy('name')->get();
        $schoolClasses = SchoolClass::orderBy('name')->get();
        $availableCards = MasterCard::where('card_type', 'member')->where('assignment_status', 'available')->get();
        $accessRules = AccessRule::all();

        return view('transactions.member.create', compact('members', 'schoolClasses', 'availableCards', 'accessRules'));
    }

    public function storeMemberTransaction(Request $request)
    {
        try {
            if (empty($request->input('start_time'))) $request->merge(['start_time' => null]);
            if (empty($request->input('end_time'))) $request->merge(['end_time' => null]);
            if (empty($request->input('update_start_time'))) $request->merge(['update_start_time' => null]);
            if (empty($request->input('update_end_time'))) $request->merge(['update_end_time' => null]);

            $baseRules = [
                'transaction_type' => 'required|in:lama,baru',
                'class_id' => 'required|exists:classes,id',
                'transaction_date' => 'required|date',
                'amount_paid' => 'required|numeric|min:0',
                'registration_fee' => 'nullable|numeric|min:0',
                'custom_total_amount' => 'required|numeric|min:0',
                'notes' => 'nullable|string|max:500',
                // Tambahan payment type untuk logika cuti
                'payment_type' => 'nullable|in:class,leave', 
            ];

            $rules = [];

            if ($request->input('transaction_type') == 'baru') {
                $newMemberRules = [
                    'name' => 'required|string|max:255',
                    'nickname' => 'nullable|string|max:255',
                    'nis' => ['nullable', 'string', 'max:50', Rule::unique('members', 'nis')->whereNull('deleted_at')],
                    'nisnas' => ['nullable', 'string', 'max:50', Rule::unique('members', 'nisnas')->whereNull('deleted_at')],
                    'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                    'master_card_id' => ['nullable', 'integer', 'exists:master_cards,id', Rule::unique('members')->whereNull('deleted_at')],
                    'join_date' => 'required|date',
                    'rule_type' => 'required_with:master_card_id|in:template,custom',
                    'access_rule_id' => 'required_if:rule_type,template|nullable|exists:access_rules,id',
                    'max_taps_per_day' => 'nullable|integer|min:0',
                    'max_taps_per_month' => 'nullable|integer|min:0',
                    'allowed_days' => 'nullable|array',
                    'start_time' => 'nullable|date_format:H:i',
                    'end_time' => 'nullable|date_format:H:i|after_or_equal:start_time',
                    'address' => 'nullable|string',
                    'phone_number' => 'nullable|string|max:20',
                    'date_of_birth' => 'nullable|date',
                    'parent_name' => 'nullable|string|max:255',
                ];
                $rules = array_merge($baseRules, $newMemberRules);
            } else { 
                $existingMemberRules = ['member_id' => 'required|exists:members,id'];
                
                if ($request->has('update_rules') && $request->update_rules == 1) {
                    $updateRules = [
                        'update_rule_type' => 'required|in:template,custom',
                        'update_access_rule_id' => 'required_if:update_rule_type,template|nullable|exists:access_rules,id',
                        'update_max_taps_per_day' => 'nullable|integer|min:0',
                        'update_max_taps_per_month' => 'nullable|integer|min:0',
                        'update_allowed_days' => 'nullable|array',
                        'update_start_time' => 'nullable|date_format:H:i',
                        'update_end_time' => 'nullable|date_format:H:i|after_or_equal:update_start_time',
                    ];
                    $existingMemberRules = array_merge($existingMemberRules, $updateRules);
                }
                $rules = array_merge($baseRules, $existingMemberRules);
            }

            $validatedData = $request->validate($rules);
            
            // Item yang DIBELI (bisa kelas latihan atau paket cuti)
            $transactionClassItem = SchoolClass::find($request->class_id);
            
            $finalTotalAmount = $validatedData['custom_total_amount'];
            $registrationFee = $validatedData['registration_fee'] ?? 0;

            $resetMessages = [];

            DB::transaction(function () use ($request, $validatedData, $transactionClassItem, $finalTotalAmount, $registrationFee, &$resetMessages) {
                $memberIdToUse = null;

                if ($request->transaction_type == 'baru') {
                    // --- MEMBER BARU ---
                    $dataMemberBaru = $validatedData;
                    // Member baru kelasnya sesuai item pertama yg dibeli
                    $dataMemberBaru['school_class_id'] = $transactionClassItem->id; 
                    
                    if ($request->hasFile('photo')) {
                        $dataMemberBaru['photo'] = $request->file('photo')->store('member_photos', 'public');
                    }

                    if ($request->rule_type == 'template' || !$request->filled('master_card_id')) {
                        $dataMemberBaru['max_taps_per_day'] = null;
                        $dataMemberBaru['max_taps_per_month'] = null;
                        $dataMemberBaru['allowed_days'] = null;
                        $dataMemberBaru['start_time'] = null;
                        $dataMemberBaru['end_time'] = null;
                    } else {
                        $dataMemberBaru['access_rule_id'] = null;
                    }

                    $dataMemberBaru['is_active'] = true; 

                    $newMember = Member::create($dataMemberBaru);
                    if ($newMember->master_card_id) {
                        MasterCard::find($newMember->master_card_id)->update(['assignment_status' => 'assigned']);
                    }
                    $memberIdToUse = $newMember->id;

                } else { 
                    // --- MEMBER LAMA ---
                    $memberIdToUse = $validatedData['member_id'];
                    $member = Member::find($memberIdToUse);

                    if ($member) {
                        $updateData = [];

                        // === LOGIKA CUTI VS KELAS ===
                        if ($request->payment_type === 'leave') {
                            // >> BAYAR CUTI
                            $updateData['is_active'] = false;
                            $resetMessages[] = 'Status member diubah menjadi CUTI (Nonaktif).';
                            
                            // Note: KELAS TIDAK BERUBAH. Member tetap pegang kelas aslinya.
                            // Item 'Cuti 100rb' hanya masuk ke tabel transaksi.
                            
                            // Opsional: Bekukan Aturan Akses (Nol-kan kuota) agar aman
                            // $updateData['rule_type'] = 'custom';
                            // $updateData['max_taps_per_day'] = 0;

                        } else {
                            // >> BAYAR KELAS (LATIHAN)
                            $updateData['is_active'] = true;
                            if(!$member->is_active) {
                                $resetMessages[] = 'Status member diaktifkan kembali.';
                            }

                            // Update Kelas jika beda (Misal upgrade atau pindah kelas)
                            if ($member->school_class_id != $transactionClassItem->id) {
                                $updateData['school_class_id'] = $transactionClassItem->id;
                                $resetMessages[] = 'Kelas member diperbarui.';
                            }
                            
                            // Reset Log Tap (Wajib saat perpanjangan)
                            $updateData['daily_tap_reset_at'] = now();
                            $updateData['monthly_tap_reset_at'] = now();
                            $resetMessages[] = 'Sisa tap member telah di-reset.';

                            // Update Rules (Hanya jika dicentang & bukan cuti)
                            if ($request->has('update_rules') && $request->update_rules == 1) {
                                $updateData['rule_type'] = $validatedData['update_rule_type'];
                                
                                if ($request->update_rule_type == 'template') {
                                    $updateData['access_rule_id'] = $validatedData['update_access_rule_id'];
                                    $updateData['max_taps_per_day'] = null;
                                    $updateData['max_taps_per_month'] = null;
                                    $updateData['allowed_days'] = null;
                                    $updateData['start_time'] = null;
                                    $updateData['end_time'] = null;
                                } else {
                                    $updateData['access_rule_id'] = null;
                                    $updateData['max_taps_per_day'] = $validatedData['update_max_taps_per_day'];
                                    $updateData['max_taps_per_month'] = $validatedData['update_max_taps_per_month'];
                                    $updateData['allowed_days'] = $validatedData['update_allowed_days'] ?? null;
                                    $updateData['start_time'] = $validatedData['update_start_time'];
                                    $updateData['end_time'] = $validatedData['update_end_time'];
                                }
                            }
                        }

                        if (!empty($updateData)) {
                            $member->update($updateData);
                        }
                    }
                }

                // Simpan Transaksi (Dengan Jam)
                $trxDate = $request->filled('transaction_date') 
                            ? Carbon::parse($request->transaction_date . ' ' . now()->format('H:i:s')) 
                            : now();

                MemberTransaction::create([
                    'member_id' => $memberIdToUse,
                    'total_amount' => $finalTotalAmount, 
                    'registration_fee' => $registrationFee,
                    'amount_paid' => $request->amount_paid,
                    'change' => $request->amount_paid - $finalTotalAmount,
                    'notes' => $request->notes,
                    'transaction_date' => $trxDate,
                ])->details()->create([
                    'purchasable_id' => $transactionClassItem->id,
                    'purchasable_type' => SchoolClass::class,
                    'quantity' => 1,
                    'price' => $transactionClassItem->price, 
                ]);
            });

            $successMessage = 'Transaksi berhasil disimpan.';
            if (!empty($resetMessages)) $successMessage .= ' ' . implode(' ', $resetMessages);

            return redirect()->route('transactions.index')->with('success', $successMessage);

        } catch (ValidationException $e) {
            $members = Member::orderBy('name')->get();
            $schoolClasses = SchoolClass::orderBy('name')->get();
            $availableCards = MasterCard::where('card_type', 'member')->where('assignment_status', 'available')->get();
            $accessRules = AccessRule::all();
            
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput()
                ->with(compact('members', 'schoolClasses', 'availableCards', 'accessRules'));
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }

    // ... (Sisa method: showNonMemberDetail, showNonMemberReceipt, createNonMemberTransaction, storeNonMemberTransaction, exportExcel tidak berubah) ...
    public function showNonMemberDetail($id) {
        $transaction = NonMemberTransaction::with(['purchasedTickets.ticketProduct'])->find($id);
        if (!$transaction) abort(404, 'Transaksi tidak ditemukan.');
        return view('transactions.non_member_detail', compact('transaction'));
    }
    public function showNonMemberReceipt($id) {
        $transaction = NonMemberTransaction::with(['purchasedTickets.ticketProduct'])->find($id);
        if (!$transaction) abort(404, 'Transaksi tidak ditemukan.');
        $qrcodes = [];
        foreach ($transaction->purchasedTickets as $purchasedTicket) {
            $qrcodes[] = QrCode::size(120)->generate($purchasedTicket->qrcode);
        }
        return view('receipts.non_member', compact('transaction', 'qrcodes') + ['tickets' => $transaction->purchasedTickets]);
    }
    public function createNonMemberTransaction() {
        $tickets = Ticket::orderBy('name')->get();
        return view('transactions.non_member.create', compact('tickets'));
    }
    public function storeNonMemberTransaction(Request $request) {
        // (Logika non member sama persis, copy dari file lama jika perlu, atau saya tulis ulang ringkasnya)
        $validatedData = $request->validate([
            'ticket_id' => 'required|exists:tickets,id',
            'quantity' => 'required|integer|min:1',
            'customer_name' => 'nullable|string|max:255',
            'amount_paid' => 'required|numeric|min:0',
        ]);
        $ticket = Ticket::find($validatedData['ticket_id']);
        $quantity = (int) $validatedData['quantity'];
        $totalAmount = $ticket->price * $quantity;
        if ($validatedData['amount_paid'] < $totalAmount) return back()->withInput()->with('error', 'Kurang bayar.');

        $trxResult = DB::transaction(function () use ($request, $ticket, $quantity, $totalAmount) {
            $now = now();
            $today = $now->format('Y-m-d');
            $datePrefix = $now->format('dmY');
            $lastTicket = NonMemberTicket::whereDate('created_at', $today)->latest('id')->first();
            $nextSeq = $lastTicket && $lastTicket->qrcode ? ((int) substr($lastTicket->qrcode, -5)) + 1 : 1;

            $trx = NonMemberTransaction::create([
                'customer_name' => $request->customer_name,
                'total_amount' => $totalAmount,
                'amount_paid' => $request->amount_paid,
                'change' => $request->amount_paid - $totalAmount,
                'transaction_date' => $now,
            ]);
            $tickets = [];
            for ($i = 0; $i < $quantity; $i++) {
                $token = $datePrefix . str_pad($nextSeq + $i, 5, '0', STR_PAD_LEFT);
                $tickets[] = NonMemberTicket::create(['non_member_transaction_id' => $trx->id, 'ticket_id' => $ticket->id, 'qrcode' => $token]);
            }
            return ['trx' => $trx, 'tickets' => $tickets];
        });
        
        // Redirect ke receipt
        return redirect()->route('non-member-receipt.show', $trxResult['trx']->id);
    }
    public function exportExcel(Request $request) {
        $filters = $request->only(['type', 'name', 'period', 'start_date', 'end_date', 'class_id']);
        $fileName = 'Laporan_Transaksi_' . now()->format('Y-m-d_H-i') . '.xlsx';
        return Excel::download(new TransactionsExport($filters), $fileName);
    }
}