@php
    $aiUiAdmin = \App\User::isUserAdmin();
@endphp
<ul class="nav nav-pills p-2" id="main-nav">
    @if($aiUiAdmin)
        <li class="nav-item">
            <a class="nav-link" href="{{ route('ai.generation.prompt') }}">
                Генерация текста
                <span class="badge badge-warning ml-1" style="font-weight:500;font-size:10px;vertical-align:middle">доработка</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="{{ route('ai.macros.index') }}">
                Макросы
                <span class="badge badge-warning ml-1" style="font-weight:500;font-size:10px;vertical-align:middle">доработка</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="{{ route('ai.stopwords.index') }}">
                Запрещённые слова
                <span class="badge badge-warning ml-1" style="font-weight:500;font-size:10px;vertical-align:middle">доработка</span>
            </a>
        </li>
    @endif
    <li class="nav-item">
        <a class="nav-link" href="{{ route('ai.generation.story') }}">
            Моя история
        </a>
    </li>
    @if($aiUiAdmin)
        <li class="nav-item">
            <a class="nav-link" href="{{ route('ai.generation.all.story') }}">
                История всех пользователей
            </a>
        </li>
    @endif
</ul>

<script>
document.addEventListener('DOMContentLoaded', function () {
    let currentUrl = window.location.href;

    document.querySelectorAll('#main-nav .nav-link').forEach(function(link) {
        let linkUrl = link.href;
        link.classList.remove('active');

        if (currentUrl === linkUrl) {
            link.classList.add('active');
        }
    });
});
</script>
