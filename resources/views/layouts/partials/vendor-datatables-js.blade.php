{{--
  Скрипты DataTables. Требует jquery.dataTables.min.js до include.
  $bundle: rb-min | rb-min-editor | responsive-core-min | monitoring-index | editor | core-only
--}}
@php
    $bundle = $bundle ?? 'rb-min';
@endphp
<script src="{{ asset('plugins/datatables-bs4/js/dataTables.bootstrap4.min.js') }}"></script>
@if($bundle === 'responsive-core-min')
    <script src="{{ asset('plugins/datatables-responsive/js/dataTables.responsive.min.js') }}"></script>
@elseif($bundle !== 'core-only')
    <script src="{{ asset('plugins/datatables-responsive/js/dataTables.responsive.min.js') }}"></script>
    <script src="{{ asset('plugins/datatables-responsive/js/responsive.bootstrap4.min.js') }}"></script>
@endif
@if(in_array($bundle, ['rb-min', 'rb-min-editor', 'monitoring-index', 'editor'], true))
    <script src="{{ asset('plugins/datatables-buttons/js/dataTables.buttons.min.js') }}"></script>
    <script src="{{ asset('plugins/datatables-buttons/js/buttons.bootstrap4.min.js') }}"></script>
@endif
@if($bundle === 'monitoring-index')
    <script src="{{ asset('plugins/datatables-fixedheader/js/dataTables.fixedHeader.min.js') }}"></script>
    <script src="{{ asset('plugins/datatables-select/js/dataTables.select.js') }}"></script>
    <script src="{{ asset('plugins/datatables-select/js/select.bootstrap4.js') }}"></script>
@endif
@if($bundle === 'rb-min-editor')
    <script src="{{ asset('plugins/datatables-select/js/dataTables.select.min.js') }}"></script>
    <script src="{{ asset('plugins/datatables-select/js/select.bootstrap4.min.js') }}"></script>
    <script src="{{ asset('plugins/datatables-editor/js/datatables_editor.min.js') }}"></script>
@elseif($bundle === 'editor')
    <script src="{{ asset('plugins/datatables-editor/js/datatables_editor.min.js') }}"></script>
@endif
