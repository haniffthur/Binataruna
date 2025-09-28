<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Dashboard</title>
    <link href="{{ asset('vendor/fontawesome-free/css/all.min.css') }}" rel="stylesheet">
    <link href="{{ asset('css/sb-admin-2.min.css') }}" rel="stylesheet">
    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@x.x.x/dist/select2-bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    @stack('styles')
    <style>
        .sidebar-brand-text {
            font-size: 1.2rem;
            font-weight: bold;
            /* Hapus atau override text-dark jika ada konflik */
            /*color: #007bff; /* <-- UBAH ATAU TAMBAHKAN BARIS INI UNTUK WARNA BIRU */
            /* Anda bisa menggunakan warna lain seperti: */
            /* color: #1e3a8a;  (biru tua) */
            color: #4e73df;
            (biru primary dari sb-admin-2) */
            /* color: blue;    (nama warna dasar) */
            white-space: nowrap;
        }

        /* Styling untuk elemen <img> logo */
        .sidebar-brand-logo {
            height: 35px;
            /* Tinggi spesifik untuk logo. Sesuaikan jika logo Anda terlalu kecil/besar. */
            width: auto;
            /* Biarkan lebar menyesuaikan secara proporsional agar gambar tidak pecah */
            max-height: 40px;
            /* Batas tinggi maksimum */
            object-fit: contain;
            /* Penting! Memastikan seluruh gambar logo terlihat tanpa terpotong atau terdistorsi */
            margin-right: 2px;
            /* Memberi jarak antara logo dan teks "BinaTaruna" */
            vertical-align: middle;
            /* Membantu penempatan vertikal agar sejajar dengan teks */
        }

        /* Styling untuk teks "BinaTaruna" */


        /* Penyesuaian untuk kontainer ikon (div.sidebar-brand-icon) */
        /* Ini biasanya sudah ditangani oleh Bootstrap classes seperti d-flex, align-items-center */
        .sidebar-brand-icon {
            display: flex;
            /* Memastikan flexbox aktif untuk alignment */
            align-items: center;
            /* Memusatkan logo secara vertikal */
            justify-content: center;
            /* Memusatkan logo secara horizontal jika hanya ada logo di dalamnya */
            height: 100%;
            /* Memastikan kontainer mengambil tinggi penuh yang tersedia */
        }

        /* Penyesuaian opsional untuk kontainer keseluruhan sidebar-brand (elemen <a>) */
        /* Anda bisa mengubah padding atau tinggi jika ingin membuat area logo lebih ramping atau lebih luas */
        .sidebar-brand.py-4 {
            padding-top: 1rem !important;
            /* Contoh: kurangi padding atas */
            padding-bottom: 1rem !important;
            /* Contoh: kurangi padding bawah */
            /* height: 55px; */
            /* Atau atur tinggi tetap jika diinginkan */
        }

        /* Shadow kanan */
        .sidebar {
            box-shadow: 5px 0 15px rgba(0, 0, 0, 0.1);
            z-index: 1030;
        }

        /* Collapse container */
        .sidebar .collapse-inner {
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 0.5rem;
            background-color: #ffffff;
            padding: 0;
            overflow: hidden;
        }

        /* Item collapse */
        .sidebar .collapse-item {
            display: block;
            width: 100%;
            padding: 0.65rem 1rem;
            color: #4e73df;
            font-size: 0.925rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            transition: background-color 0.2s ease, color 0.2s ease;
        }

        /* Hover & Active Style */
        .sidebar .collapse-item:hover {
            background-color: #f1f3f9;
            color: #2e59d9;
            text-decoration: none;
        }

        .sidebar .collapse-item.active {
            background-color: #e8edfb;
            font-weight: 600;
            color: #224abe;
        }
    </style>

</head>

<body id="page-top">


    <div id="wrapper">
        @include('layouts.sidebar')

        <div id="content-wrapper" class="d-flex flex-column bg-white">
            <div id="content">
                @include('layouts.topbar')


                <div class="container-fluid">
                    @yield('content')
                </div>
            </div>

            @include('layouts.footer')
        </div>
    </div>

    <script src="{{ asset('vendor/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('vendor/jquery-easing/jquery.easing.min.js') }}"></script>
    <script src="{{ asset('js/sb-admin-2.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Scripts dari halaman spesifik akan dimuat di sini -->
    @stack('scripts')
    <script>
        $(document).ready(function () {
            // Event listener untuk input pencarian di sidebar
            $('#sidebar-search-input').on('keyup', function () {
                var searchTerm = $(this).val().toLowerCase(); // Ambil teks pencarian, ubah ke huruf kecil

                // Loop melalui setiap item menu utama di sidebar
                $('#accordionSidebar > .nav-item').each(function () {
                    var menuText = $(this).text().toLowerCase(); // Ambil teks dari item menu

                    // Periksa apakah teks menu mengandung teks pencarian
                    if (menuText.indexOf(searchTerm) > -1) {
                        $(this).slideDown(); // Jika cocok, tampilkan
                    } else {
                        $(this).slideUp(); // Jika tidak cocok, sembunyikan
                    }
                });
            });
        });
    </script>
</body>

</html>