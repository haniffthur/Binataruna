<?php

namespace App\Http\Controllers;

use App\Models\MasterCard;
use Illuminate\Http\Request;

class MasterCardController extends Controller
{
    public function index(Request $request)
    {
        // Ambil filter dari request
        $cardType = $request->input('card_type');
        $assignmentStatus = $request->input('assignment_status');
        $search = $request->input('search'); // Untuk pencarian berdasarkan UID Kartu

        // Mulai query dengan semua kartu
        $query = MasterCard::latest();

        // Terapkan filter jika ada
        if ($cardType) {
            $query->where('card_type', $cardType);
        }

        if ($assignmentStatus) {
            $query->where('assignment_status', $assignmentStatus);
        }

        if ($search) {
            $query->where('cardno', 'like', '%' . $search . '%');
        }

        $cards = $query->paginate(20)->withQueryString(); // Tambahkan withQueryString() agar filter tetap aktif saat navigasi halaman

        // Dapatkan jumlah untuk kartu Anda (tetap sama)
        $totalCards = MasterCard::count();
        $availableCards = MasterCard::where('assignment_status', 'available')->count();
        $assignedCards = MasterCard::where('assignment_status', 'assigned')->count();

        return view('master_cards.index', compact('cards', 'totalCards', 'availableCards', 'assignedCards', 'cardType', 'assignmentStatus', 'search'));
    }

    public function create()
    {
        return view('master_cards.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'cardno' => 'required|string|unique:master_cards,cardno',
            'card_type' => 'required|in:member,staff,coach',
        ]);

        MasterCard::create($request->all());

        return redirect()->route('master-cards.index')->with('success', 'Kartu baru berhasil ditambahkan.');
    }

    public function show(MasterCard $masterCard)
    {
        return view('master_cards.show', compact('masterCard'));
    }

    public function edit(MasterCard $masterCard)
    {
        return view('master_cards.edit', compact('masterCard'));
    }

    public function update(Request $request, MasterCard $masterCard)
    {
        $request->validate([
            'cardno' => 'required|string|unique:master_cards,cardno,' . $masterCard->id,
            'card_type' => 'required|in:member,staff,coach',
            'assignment_status' => 'required|in:available,assigned',
        ]);

        $masterCard->update($request->all());

        return redirect()->route('master-cards.index')->with('success', 'Data kartu berhasil diperbarui.');
    }

    public function destroy(MasterCard $masterCard)
    {
        if ($masterCard->assignment_status === 'assigned') {
            return back()->with('error', 'Tidak bisa menghapus kartu yang sedang digunakan.');
        }

        $masterCard->delete();

        return redirect()->route('master-cards.index')->with('success', 'Kartu berhasil dihapus.');
    }
}