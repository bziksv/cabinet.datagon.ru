<link rel="stylesheet" href="{{ asset('plugins/codemirror/codemirror.css') }}">
<link rel="stylesheet" href="{{ asset('plugins/codemirror/theme/monokai.css') }}">
<link rel="stylesheet" href="{{ asset('plugins/jquery-ui/jquery-ui.css') }}">
@include('layouts.partials.vendor-datatables-css', ['bundle' => 'rb-min'])
<link rel="stylesheet" href="{{ asset('css/cabinet-http-headers.css') }}?v={{ @filemtime(public_path('css/cabinet-http-headers.css')) ?: time() }}">
