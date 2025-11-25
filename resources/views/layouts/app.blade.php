<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Sistem Manajemen Member & Absensi RFID">
    <meta name="author" content="Bina Taruna">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Dashboard') - Bina Taruna</title>

    <!-- Custom fonts & styles -->
    <link href="{{ asset('vendor/fontawesome-free/css/all.min.css') }}" rel="stylesheet" type="text/css">
    <link
        href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i"
        rel="stylesheet">
    <link href="{{ asset('css/sb-admin-2.min.css') }}" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.5.2/dist/select2-bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    @stack('styles')

    <style>
        /* -- SIDEBAR STYLES -- */
        /* Styling Logo di Sidebar */
        .sidebar-brand-text {
            font-size: 1.2rem;
            font-weight: bold;
            color: #4e73df;
            white-space: nowrap;
        }

        .sidebar-brand-logo {
            height: 35px;
            width: auto;
            max-height: 40px;
            object-fit: contain;
            margin-right: 5px;
            vertical-align: middle;
        }

        .sidebar-brand-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
        }

        .sidebar-brand.py-4 {
            padding-top: 1rem !important;
            padding-bottom: 1rem !important;
        }

        /* Perbaikan Tampilan Sidebar */
        .sidebar {
            box-shadow: 5px 0 15px rgba(0, 0, 0, 0.1);
            z-index: 1030;
        }

        .sidebar .collapse-inner {
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 0.5rem;
            background-color: #ffffff;
            padding: 0;
            overflow: hidden;
        }

        .sidebar .collapse-item {
            display: block;
            width: 100%;
            padding: 0.65rem 1rem;
            color: #4e73df;
            font-size: 0.925rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            transition: background-color 0.2s ease, color 0.2s ease;
        }

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

        /* ============================================================= */
        /* MODERN TOAST NOTIFICATION + ANIMATION            */
        /* ============================================================= */

        /* Container Tengah Atas */
        #tapping-toast-container {
            position: fixed;
            top: 30px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 99999;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
            pointer-events: none;
            /* Agar klik tembus ke belakang jika tidak kena card */
        }

        /* Card Modern Glass */
        .tapping-toast-item {
            pointer-events: auto;
            width: 480px;
            /* Lebar ideal */
            max-width: 90vw;
            background: #fff;
            border-radius: 12px;
            /* Sudut membulat */
            padding: 0;
            /* Padding 0 karena kita atur layout flex */
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            /* Shadow halus */
            animation: slideDown 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            /* Efek membal dikit */
            position: relative;
            overflow: hidden;
            /* PENTING: Agar progress bar tidak keluar dari radius */
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .toast-body-content {
            padding: 16px 20px;
            position: relative;
            z-index: 2;
            /* Di atas progress bar */
        }

        /* Animasi Masuk */
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Animasi Keluar */
        .toast-hide {
            animation: fadeOutUp 0.5s forwards;
        }

        @keyframes fadeOutUp {
            to {
                opacity: 0;
                transform: translateY(-50px);
            }
        }

        /* PROGRESS BAR ANIMATION */
        .toast-progress-bar {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 5px;
            /* Ketebalan garis */
            width: 100%;
            z-index: 1;
            /* Animasi berjalan linear selama 4.5 detik (sesuai timeout JS) */
            animation: progressLine 4.5s linear forwards;
        }

        @keyframes progressLine {
            0% {
                width: 100%;
            }

            100% {
                width: 0%;
            }
        }

        /* Warna Progress Bar */
        .bg-progress-success {
            background-color: #1cc88a;
        }

        .bg-progress-error {
            background-color: #e74a3b;
        }

        /* Typography */
        .toast-title {
            font-size: 0.8rem;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .toast-name {
            font-size: 1.1rem;
            font-weight: 700;
            color: #2e2e2e;
            line-height: 1.2;
        }

        .toast-detail {
            font-size: 0.85rem;
            color: #666;
            margin-top: 4px;
        }

        .toast-icon-container {
            display: flex;
            align-items: center;
            justify-content: center;
            padding-right: 15px;
            border-right: 1px solid #eee;
            margin-right: 15px;
        }
    </style>
</head>

<body id="page-top">

    <!-- Page Wrapper -->
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

    <a class="scroll-to-top rounded" href="#page-top"><i class="fas fa-angle-up"></i></a>

    <!-- Scripts -->
    <script src="{{ asset('vendor/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('vendor/jquery-easing/jquery.easing.min.js') }}"></script>
    <script src="{{ asset('js/sb-admin-2.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    @stack('scripts')

    <!-- Container Notifikasi -->
    <div id="tapping-toast-container"></div>

    <!-- ============================================================= -->
    <!--                  REAL-TIME NOTIFIKASI (POLLING)               -->
    <!-- ============================================================= -->

    <script>
        $(document).ready(function () {
            let lastLogId = 0;
            let isFirstLoad = true;

            function checkLatestTap() {
                $.ajax({
                    url: '{{ route("api.tap-logs.latest") }}',
                    method: 'GET',
                    data: { since_id: lastLogId },
                    success: function (logs) {
                        if (!Array.isArray(logs) || logs.length === 0) return;

                        const maxId = Math.max(...logs.map(l => l.id));
                        if (maxId > lastLogId) lastLogId = maxId;

                        if (isFirstLoad) {
                            isFirstLoad = false;
                            return;
                        }

                        logs.forEach(function (log) {
                            showNotification(log);
                        });
                    }
                });
            }

            function showNotification(data) {
                const isSuccess = data.status == 1;

                // Variabel tampilan
                const progressClass = isSuccess ? 'bg-progress-success' : 'bg-progress-error';
                const textClass = isSuccess ? 'text-success' : 'text-danger';
                const iconClass = isSuccess ? 'fa-check-circle' : 'fa-times-circle';
                const titleText = isSuccess ? 'AKSES DITERIMA' : 'AKSES DITOLAK';

                let timeString = data.tapped_at;
                if (timeString.includes(',')) {
                    timeString = timeString.split(',')[1].trim();
                }

                // Template HTML dengan Progress Bar Animasi
                const toastHtml = `
                <div class="tapping-toast-item">
                    <div class="toast-body-content">
                        <div class="d-flex align-items-center">
                            <!-- Ikon -->
                            <div class="toast-icon-container">
                                <i class="fas ${iconClass}" style="font-size:32px; color:${isSuccess ? '#1cc88a' : '#e74a3b'}"></i>
                            </div>
                            
                            <!-- Teks -->
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="toast-title ${textClass}">${titleText}</div>
                                    <div class="small text-gray-500"><i class="far fa-clock mr-1"></i>${timeString}</div>
                                </div>
                                <div class="toast-name">${data.owner_name}</div>
                                <div class="toast-detail">
                                    <span class="badge badge-light border mr-1">${data.owner_type}</span>
                                    <span>${data.owner_detail}</span>
                                </div>
                                <div class="text-xs text-muted mt-1 font-italic">
                                    "${data.message}"
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Progress Bar Animasi di Bawah -->
                    <div class="toast-progress-bar ${progressClass}"></div>
                </div>
            `;

                const $toast = $(toastHtml);
                $('#tapping-toast-container').append($toast);

                // Hapus otomatis sesuai durasi animasi (4.5s delay + 0.5s fadeout)
                setTimeout(() => {
                    $toast.addClass('toast-hide'); // Trigger animasi keluar
                    setTimeout(() => $toast.remove(), 500); // Hapus dari DOM setelah animasi selesai
                }, 4500);
            }

            checkLatestTap();
            setInterval(checkLatestTap, 3000);
        });
    </script>

</body>

</html>