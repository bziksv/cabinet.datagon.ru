@if(\App\Support\DemoCabinet::isCurrentUser())
    <div class="cabinet-demo-banner" role="status">
        <div class="cabinet-demo-banner__inner">
            <p class="cabinet-demo-banner__text mb-0">
                <strong>Демо-кабинет</strong>
                — только просмотр готовых результатов. Запуски, сохранения и изменения отключены.
            </p>
            <div class="cabinet-demo-banner__actions">
                <a href="{{ url('/register') }}" class="btn btn-sm btn-primary">Регистрация</a>
                <form method="post" action="{{ route('demo-cabinet.exit') }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-secondary">Выйти из демо</button>
                </form>
            </div>
        </div>
    </div>
@endif
