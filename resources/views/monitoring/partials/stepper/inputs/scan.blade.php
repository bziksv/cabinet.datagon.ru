<p class="cabinet-mon-create-hint-step">{{ __('Monitoring v2 create step scan hint') }}</p>
<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Настройка времени снятия позиций</h3>
            </div>
            <!-- /.card-header -->
            <div class="card-body">
                <div class="callout callout-warning">
                    <p class="mb-2">{{ __('Monitoring v2 create scan schedule lead') }}</p>
                    <p class="mb-2">
                        {!! __('Monitoring v2 create scan schedule any mode') !!}
                    </p>
                    <ul class="mb-2 ps-3 small">
                        <li>{{ __('Monitoring v2 create scan mode times') }}</li>
                        <li>{{ __('Monitoring v2 create scan mode weeks') }}</li>
                        <li>{{ __('Monitoring v2 create scan mode months') }}</li>
                        <li>{{ __('Monitoring v2 create scan mode ranges') }}</li>
                    </ul>
                    <p class="mb-0 small text-secondary">{{ __('Monitoring v2 create scan schedule manual') }}</p>
                </div>

                <div class="form-group">
                    <label>Режимы</label>
                    <select id="mode-scan" class="form-select">
                        <option value="times">Каждый день</option>
                        <option value="months">Каждый месяц</option>
                        <option value="weeks">По дням</option>
                        <option value="ranges">Периодами (Через определенное количество дней)</option>
                    </select>
                </div>

                <div class="" id="callout-info"></div>

                <div class="mode-scan"></div>

            </div>
            <!-- /.card-body -->
        </div>
        <!-- /.card -->
    </div>
</div>
