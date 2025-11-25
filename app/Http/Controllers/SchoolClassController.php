<?php

namespace App\Http\Controllers;

use App\Models\SchoolClass;
use App\Models\AccessRule; // <-- Jangan lupa import model AccessRule
use Illuminate\Http\Request;

class SchoolClassController extends Controller
{
    /**
     * Menampilkan daftar semua kelas.
     */
    public function index()
    {
        // Mengambil data kelas beserta relasi aturan aksesnya (Eager Loading)
        // Diurutkan berdasarkan nama secara ascending
        $classes = SchoolClass::with('accessRule')->orderBy('name', 'asc')->paginate(10);

        return view('classes.index', compact('classes'));
    }

    /**
     * Menampilkan form untuk membuat kelas baru.
     */
    public function create()
    {
        // Mengambil semua aturan akses untuk dropdown pilihan
        $accessRules = AccessRule::orderBy('name')->get();

        return view('classes.create', compact('accessRules'));
    }

    /**
     * Menyimpan kelas baru ke database.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:classes,name',
            'price' => 'required|numeric|min:0',
            // Validasi untuk field baru access_rule_id
            'access_rule_id' => 'nullable|integer|exists:access_rules,id', 
        ], [
            'name.required' => 'Nama kelas wajib diisi.',
            'name.unique' => 'Nama kelas ini sudah terdaftar.',
            'price.required' => 'Harga kelas wajib diisi.',
            'price.min' => 'Harga tidak boleh negatif.',
        ]);

        SchoolClass::create([
            'name' => $request->name,
            'price' => $request->price,
            'access_rule_id' => $request->access_rule_id, // Simpan ID aturan
        ]);

        return redirect()->route('classes.index')
            ->with('success', 'Kelas baru berhasil ditambahkan.');
    }

    /**
     * Menampilkan detail kelas (jika diperlukan).
     * Saat ini biasanya tidak dipakai di CRUD sederhana, tapi disiapkan saja.
     */
    public function show(SchoolClass $class)
    {
        return view('classes.show', compact('class'));
    }

    /**
     * Menampilkan form untuk mengedit kelas.
     */
    public function edit(SchoolClass $class) // Menggunakan Route Model Binding
    {
        // Mengambil semua aturan akses untuk dropdown
        $accessRules = AccessRule::orderBy('name')->get();

        return view('classes.edit', compact('class', 'accessRules'));
    }

    /**
     * Mengupdate data kelas di database.
     */
    public function update(Request $request, SchoolClass $class)
    {
        $request->validate([
            // Unique check mengabaikan ID kelas ini sendiri saat update
            'name' => 'required|string|max:255|unique:classes,name,' . $class->id,
            'price' => 'required|numeric|min:0',
            'access_rule_id' => 'nullable|integer|exists:access_rules,id',
        ], [
            'name.required' => 'Nama kelas wajib diisi.',
            'name.unique' => 'Nama kelas ini sudah terdaftar.',
            'price.required' => 'Harga kelas wajib diisi.',
        ]);

        $class->update([
            'name' => $request->name,
            'price' => $request->price,
            'access_rule_id' => $request->access_rule_id,
        ]);

        return redirect()->route('classes.index')
            ->with('success', 'Data kelas berhasil diperbarui.');
    }

    /**
     * Menghapus kelas dari database.
     */
    public function destroy(SchoolClass $class)
    {
        // Cek apakah kelas ini masih dipakai oleh member aktif
        // Menggunakan method 'members' yang diasumsikan ada di model SchoolClass
        if ($class->members()->count() > 0) {
            return redirect()->route('classes.index')
                ->with('error', 'Gagal menghapus! Masih ada member yang terdaftar di kelas ini.');
        }

        $class->delete();

        return redirect()->route('classes.index')
            ->with('success', 'Kelas berhasil dihapus.');
    }
}