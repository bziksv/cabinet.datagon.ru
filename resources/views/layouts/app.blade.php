@php use App\Session; @endphp
    <!DOCTYPE html>
<html lang="en">
<head>
    @if(config('app.env') !== 'local')
    <!-- Google Tag Manager -->
    <script>(function (w, d, s, l, i) {
            w[l] = w[l] || [];
            w[l].push({
                'gtm.start':
                    new Date().getTime(), event: 'gtm.js'
            });
            var f = d.getElementsByTagName(s)[0],
                j = d.createElement(s), dl = l != 'dataLayer' ? '&l=' + l : '';
            j.async = true;
            j.src =
                'https://www.googletagmanager.com/gtm.js?id=' + i + dl;
            f.parentNode.insertBefore(j, f);
        })(window, document, 'script', 'dataLayer', 'GTM-PS4GF7H');</script>
    <!-- End Google Tag Manager -->
    @endif
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="shortcut icon" href="{{ asset('img/favicon.svg') }}"/>
    <title>@yield('title')</title>
    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="{{ asset('plugins/fontawesome-free/css/all.min.css') }}">
    <!-- IonIcons -->
    <link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="{{ asset('css/adminlte.min.css') }}">
    <!-- Node modules style -->
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">

    @yield('css')
    <style>
        .main-sidebar.sidebar-dark-primary.elevation-4 {
            position: fixed;
            height: 100vh;
        }

        .nav-sidebar > .nav-item .nav-icon {
            margin-left: 0 !important;
            font-size: 1.2rem !important;
            margin-right: 0 !important;
            text-align: center !important;
            width: 1rem !important;
        }

    </style>
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    {{-- jQuery в head: sidebar/menu-right и др. inline-скрипты в body иначе падают с "$ is not defined" --}}
    <script src="{{ asset('plugins/jquery/jquery.min.js') }}"></script>
</head>

<body class="@if(config('app.env') === 'local')sidebar-mini @else hold-transition sidebar-mini @endif">
@if(config('app.env') !== 'local')
<!-- Google Tag Manager (noscript) -->
<noscript>
    <iframe src="https://www.googletagmanager.com/ns.html?id=GTM-PS4GF7H"
            height="0" width="0" style="display:none;visibility:hidden"></iframe>
</noscript>
<!-- End Google Tag Manager (noscript) -->
@endif
<div class="wrapper">
    <nav class="main-header navbar navbar-expand navbar-white navbar-light" id="header-nav-bar">
        @include('navigation.menu')
        <ul class="navbar-nav ml-auto">
            @include('navigation.menu-right')
            <li class="nav-item">
                <a class="nav-link" data-widget="fullscreen" href="#" role="button">
                    <i class="fas fa-expand-arrows-alt"></i>
                </a>
            </li>
            <li class="nav-item dropdown">
                {!! Form::open(['class' => '', 'method' => 'POST', 'route' => ['logout']]) !!}
                {!! Form::button( '<i class="fas fa-sign-out-alt"></i>', ['type' => 'submit', 'class' => 'nav-link border-0']) !!}
                {!! Form::close() !!}
            </li>
        </ul>
    </nav>
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="{{ route('home') }}" class="brand-link cabinet-brand">
            <img src="{{ asset('img/logo-icon.svg') }}" alt="" class="brand-image cabinet-brand__icon">
            <span class="brand-text">Датагон</span>
        </a>
        <div class="sidebar">
            @auth
                @include('users.panel')
            @endauth
                @include('navigation.sidebar')
        </div>
    </aside>
    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">

        <!-- Main content -->
        <div class="content pt-3 pb-3" id="app">
            <div class="container-fluid">
                @yield('content')
            </div>
            <!-- /.container-fluid -->
        </div>
        <!-- /.content -->
    </div>
    <!-- /.content-wrapper -->
    <!-- Main Footer -->
    <footer class="main-footer" id="main-footer">
        <strong>&copy; 2021&ndash;{{ date('Y') }} <a href="https://datagon.ru/">Датагон</a>. Все права защищены.</strong>
    </footer>
</div>
<!-- ./wrapper -->

<!-- REQUIRED SCRIPTS -->
<!-- Bootstrap -->
<script src="{{ asset('plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>

@if(request()->route()->parameter('statistic_project_id') !== null)
    <script>
        const tracking_project_id = "{{ request()->route()->parameter('statistic_project_id') }}";

        $(document).on('click', '.click_tracking', function () {
            $.ajax({
                type: 'post',
                url: "{{ route('click.tracking') }}",
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    button_text: $(this).attr('data-click'),
                    url: location.href,
                    project_id: tracking_project_id
                },
                success: function (response) {
                },
                error: function () {
                }
            })
        });
    </script>
@endif

<script>
    $(document).ready(function () {
        let player;

        $('#video-course').on('click', function () {
            player = new YT.Player('video-course', {
                videoId: $(this).attr('data-id'),
                playerVars: {
                    'autoplay': 1,
                },
                events: {
                    'onReady': onPlayerReady
                }
            });
        })

        $('.video-course').on('click', function () {
            player = new YT.Player($(this).attr('id'), {
                videoId: $(this).attr('data-id'),
                playerVars: {
                    'autoplay': 1,
                },
                events: {
                    'onReady': onPlayerReady
                }
            });
        })

        function onPlayerReady(event) {
            event.target.playVideo();
        }
    })
</script>

<!-- app -->
@unless(request()->path() == 'utm-marks' || request()->path() == 'all-projects')
    @if(config('app.env') === 'local')
        <script>window.__DISABLE_LARAVEL_ECHO__ = true;</script>
        <script src="{{ asset('js/echo-disable-pre.js') }}"></script>
    @else
        <script>
            window.__LARAVEL_ECHO_KEY__ = @json(config('broadcasting.connections.pusher.key'));
            window.__LARAVEL_ECHO_PORT__ = @json(env('LARAVEL_WEBSOCKETS_PORT', 6001));
            window.__LARAVEL_ECHO_TLS__ = @json(filter_var(env('PUSHER_APP_TLS', true), FILTER_VALIDATE_BOOLEAN));
        </script>
    @endif
    @if(config('app.env') === 'local')
        <script src="{{ asset('js/app.js') }}?v=no-ws-{{ @filemtime(public_path('js/app.js')) }}"></script>
    @else
        <script src="{{ mix('js/app.js') }}"></script>
    @endif
@endunless
<!-- AdminLTE -->
<script src="{{ asset('js/adminlte.js') }}"></script>
@include('partials.cabinet-layout-scripts')

@if(optional(Auth::user())->statistic && ! cabinet_skip_heavy_web())
    <script>
        let secondsTrackingRedbox = 0;
        let timeTrackingRedboxInterval

        timeTrackingRedboxInterval = startTracking()

        $(window).bind('focus', function () {
            clearInterval(timeTrackingRedboxInterval)
            timeTrackingRedboxInterval = startTracking()
        });

        $(window).bind('blur', function () {
            clearInterval(timeTrackingRedboxInterval)
            updateStatistics(secondsTrackingRedbox)
        });

        window.onbeforeunload = function () {
            clearInterval(timeTrackingRedboxInterval)
            updateStatistics(secondsTrackingRedbox)
        };

        function startTracking() {
            return setInterval(() => {
                secondsTrackingRedbox += 1;
                if (secondsTrackingRedbox === 300) {
                    updateStatistics(secondsTrackingRedbox)
                }
            }, 1000)
        }

        function updateStatistics() {
            $.ajax({
                url: "{{ route('update.statistics') }}",
                method: 'POST',
                data: {
                    seconds: secondsTrackingRedbox,
                    controllerAction: "{{ $controllerAction }}",
                    _token: $('meta[name="csrf-token"]').attr('content'),
                },
            });

            secondsTrackingRedbox = 0;
        }
    </script>
@endif

<!-- OPTIONAL SCRIPTS -->
{{--<script src="{{ asset('plugins/chart.js/Chart.min.js') }}"></script>--}}

@if(config('app.env') !== 'local')
<script src="{{ asset('js/demo.js') }}"></script>
@endif
@yield('js')

<span class="click_tracking another_action"></span>

<script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
<script>
    $(document).ready(function () {
        $(".x-drop-down__value").click(function (event) {
            toggleMenu();
            event.stopPropagation();
        });

        $('.xx1').click(function () {
            $('.x-drop-down__value').html($(this).text());
            toggleMenu();
        });

        function toggleMenu() {
            let menu = $(".x-drop-down__dropped");
            if (!menu.hasClass('active')) {
                window.addEventListener('click', closeMenu);
            } else {
                window.removeEventListener('click', closeMenu);
            }
            menu.toggleClass("active");
        }

        function closeMenu() {
            $(".x-drop-down__dropped").removeClass("active")
        }

        $('.x-drop-down__dropped').click(function (event) {
            event.stopPropagation();
        });

        $('.x-input__field').on('input', function () {
            let search = $(this).val();
            searchData(search);
        });

        function searchData(search) {
            let items = $('.nav-link.search-link');
            items.each(function () {
                if ($(this).text().toLowerCase().indexOf(search.toLowerCase()) === -1) {
                    $(this).addClass('item_hide');
                } else {
                    $(this).removeClass('item_hide');
                }
            });
        }
    });
</script>
@include('flash::message')

@if(!config('app.debug'))
    <!-- Yandex.Metrika counter -->
    <script type="text/javascript">
        (function (m, e, t, r, i, k, a) {
            m[i] = m[i] || function () {
                (m[i].a = m[i].a || []).push(arguments)
            };
            m[i].l = 1 * new Date();
            k = e.createElement(t), a = e.getElementsByTagName(t)[0], k.async = 1, k.src = r, a.parentNode.insertBefore(k, a)
        })
        (window, document, "script", "https://mc.yandex.ru/metrika/tag.js", "ym");

        ym(89500732, "init", {
            clickmap: true,
            trackLinks: true,
            accurateTrackBounce: true,
            webvisor: true
        });
    </script>
    <noscript>
        <div><img src="https://mc.yandex.ru/watch/89500732" style="; left:-9999px;" alt=""/></div>
    </noscript>
    <!-- /Yandex.Metrika counter -->
@endif
</body>
</html>
