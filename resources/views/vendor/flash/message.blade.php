@foreach (session('flash_notification', collect())->toArray() as $message)
    <script>
        $(function () {
            if (typeof $.fn.Toasts === 'function') {
                $(document).Toasts('create', {
                    class: 'bg-' + "{{ $message['level']}}",
                    title: "{{ $message['title'] }}",
                    subtitle: "{{ __('Close') }}",
                    body: "{{ $message['message'] }}",
                    autohide: true,
                    delay: 5000,
                });
            }
        });
    </script>
@endforeach

{{ session()->forget('flash_notification') }}
