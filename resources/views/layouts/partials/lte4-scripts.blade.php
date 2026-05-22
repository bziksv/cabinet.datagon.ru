@php $html = asset('html'); @endphp
<script src="{{ $html }}/npm/overlayscrollbars@2.11.0/browser/overlayscrollbars.browser.es6.min.js" crossorigin="anonymous"></script>
<script src="{{ $html }}/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" crossorigin="anonymous"></script>
<script src="{{ $html }}/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js" crossorigin="anonymous"></script>
<script src="{{ $html }}/js/adminlte.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const sidebarWrapper = document.querySelector('.sidebar-wrapper');
        const isMobile = window.innerWidth <= 992;
        if (
            sidebarWrapper &&
            typeof OverlayScrollbarsGlobal !== 'undefined' &&
            OverlayScrollbarsGlobal.OverlayScrollbars &&
            !isMobile
        ) {
            OverlayScrollbarsGlobal.OverlayScrollbars(sidebarWrapper, {
                scrollbars: {
                    theme: 'os-theme-light',
                    autoHide: 'leave',
                    clickScroll: true,
                },
            });
        }
    });
</script>
