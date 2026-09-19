@extends('layouts.app')

@section('title', __('API keys'))

@section('content')
    <div class="container-fluid py-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h2 class="h4 mb-0">
                <i class="bi bi-key me-2 text-primary" aria-hidden="true"></i>{{ __('API keys') }}
            </h2>
            <a href="{{ route('profile.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('Profile') }}</a>
        </div>

        <p class="text-secondary small mb-3">
            Ключи для модулей магазинов (релевантность и генерация текстов). Передавайте заголовок
            <code>Authorization: Bearer titlo_…</code>.
        </p>

        @if(!empty($tokenStats))
            <div class="alert alert-light border mb-3">
                <div class="d-flex flex-wrap gap-4 align-items-baseline">
                    <div>
                        <div class="text-muted small">Токены генерации (ваш аккаунт)</div>
                        <div class="h4 mb-0">{{ number_format((int) $tokenStats['tokens'], 0, '', ' ') }}</div>
                    </div>
                    <div>
                        <div class="text-muted small">Запросов</div>
                        <div class="h5 mb-0">{{ number_format((int) $tokenStats['requests'], 0, '', ' ') }}</div>
                    </div>
                    <div class="align-self-center">
                        <a href="{{ route('ai.generation.story') }}" class="btn btn-outline-secondary btn-sm">История генераций</a>
                    </div>
                </div>
            </div>
        @endif

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if(!empty($plainKey))
            <div class="alert alert-warning">
                <strong>Скопируйте ключ сейчас</strong> — повторно он не показывается:
                <div class="mt-2">
                    <code class="user-select-all" style="word-break: break-all;">{{ $plainKey }}</code>
                </div>
            </div>
        @endif

        <div class="card mb-3">
            <div class="card-body">
                <form method="post" action="{{ route('integration.api-keys.store') }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-8">
                        <label class="form-label" for="api-key-name">{{ __('Name') }}</label>
                        <input type="text" class="form-control" id="api-key-name" name="name"
                               value="{{ old('name') }}" maxlength="120" required
                               placeholder="Например: магазин">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary w-100">{{ __('Create') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead>
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>Prefix</th>
                        <th>{{ __('Created') }}</th>
                        <th>{{ __('Last used') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($keys as $key)
                        <tr>
                            <td>{{ $key->name }}</td>
                            <td><code>{{ $key->prefix }}…</code></td>
                            <td>{{ optional($key->created_at)->format('d.m.Y H:i') }}</td>
                            <td>{{ $key->last_used_at ? $key->last_used_at->format('d.m.Y H:i') : '—' }}</td>
                            <td>
                                @if($key->revoked_at)
                                    <span class="badge bg-secondary">{{ __('Revoked') }}</span>
                                @else
                                    <span class="badge bg-success">{{ __('Active') }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if(!$key->revoked_at)
                                    <form method="post" action="{{ route('integration.api-keys.destroy', $key->id) }}"
                                          onsubmit="return confirm('Отозвать ключ?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm">{{ __('Revoke') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-secondary">Ключей пока нет.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-body small text-secondary">
                <div class="fw-semibold text-dark mb-1">API base</div>
                <code>{{ url('/api/v1') }}</code>
                <ul class="mt-2 mb-0">
                    <li><code>POST /relevance/analyses</code> — запуск анализа</li>
                    <li><code>GET /relevance/analyses/{id}</code> — статус</li>
                    <li><code>GET /relevance/histories/{id}/missing-phrases</code></li>
                    <li><code>POST /ai/generate</code> — category | preview | detail | phrase (или <code>items[]</code> до 30 фраз)</li>
                    <li><code>POST /relevance/batches</code> — пачка до 100 items</li>
                </ul>
            </div>
        </div>
    </div>
@endsection
