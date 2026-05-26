@if ($errors->any())
    <div class="alert alert-danger py-2 mb-3" role="alert">
        <ul class="mb-0 ps-3 small">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
