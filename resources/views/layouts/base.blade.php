<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="apple-touch-icon" sizes="76x76" href="../../assets/img/apple-touch-icon.png">
    <link rel="icon" type="image/png" href="../../assets/img/favicon.ico">
    <title>
        Dayli Admin
    </title>
    <!-- Metas -->
    @if (config('app.is_demo'))
    <x-demo-metas></x-demo-metas>
    @endif
    <!--     Fonts and icons     -->
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700" rel="stylesheet" />
    <!-- Nucleo Icons -->
    <link href="../../assets/css/nucleo-icons.css" rel="stylesheet" />
    <link href="../../assets/css/nucleo-svg.css" rel="stylesheet" />
    <!-- Font Awesome Icons -->
    <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>
    <link href="../../assets/css/nucleo-svg.css" rel="stylesheet" />
    <link href="../../assets/css/leaflet.css" crossorigin="" rel="stylesheet">
    <!-- CSS Files -->
    <link href="../../assets/css/soft-ui-dashboard-pro.css?v=2" rel="stylesheet" />
    <!-- Quill -->
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <!-- Bootstrap Tour -->
    <!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous"> -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>

    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>

    @livewireStyles
</head>

<body
    class="g-sidenav-show {{ strpos(Request::route()->uri(), 'authentication') === false or (!in_array(request()->route()->getName(), ['forgot-password', 'reset-password']) ? 'bg-gray-100' : '') }} ">

    {{ $slot }}
    <script src="https://code.jquery.com/jquery-3.6.0.js"></script>
    <!--   Core JS Files   -->
    <script src="../../../assets/js/core/popper.min.js"></script>
    <!-- <script src="../../../assets/js/core/bootstrap.min.js"></script> -->
    <script src="../../../assets/js/plugins/choices.min.js"></script>
    <script src="../../../assets/js/plugins/smooth-scrollbar.min.js"></script>

    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <!-- <script src="//cdn.quilljs.com/1.3.6/quill.min.js"></script> -->
    <!-- Kanban scripts -->
    <script src="../../../assets/js/plugins/dragula/dragula.min.js"></script>
    <script src="../../../assets/js/plugins/jkanban/jkanban.js"></script>
    <script>
        var win = navigator.platform.indexOf('Win') > -1;
        if (win && document.querySelector('#sidenav-scrollbar')) {
            var options = {
                damping: '0.5'
            }
            Scrollbar.init(document.querySelector('#sidenav-scrollbar'), options);
        }
    </script>
    <!-- Bootstrap tourist -->
    {{-- <script src="https://code.jquery.com/ui/1.13.0/jquery-ui.js"></script> --}}
    <script src="../../../assets/js/plugins/bootstrap-tour.js"></script>
    {{-- <script src="../js/tour.js"></script> --}}
    <!-- Github buttons -->
    <script async defer src="https://buttons.github.io/buttons.js"></script>
    <!-- Control Center for Soft Dashboard: parallax effects, scripts for the example pages etc -->
    <script src="/assets/js/soft-ui-dashboard.js?v=2"></script>

    @livewireScripts


</body>

</html>