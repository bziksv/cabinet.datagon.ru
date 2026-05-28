<p class="cabinet-mon-create-hint-step">{{ __('Monitoring v2 create step keywords hint') }}</p>
<div class="row">
    <div class="col-lg-5">
        <div class="card card-outline card-secondary h-100">
            <div class="card-header">
                <h3 class="card-title mb-0">{{ __('Monitoring v2 create keywords card') }}</h3>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label for="textarea-keywords">{{ __('Monitoring v2 create keywords textarea') }}</label>
                    <textarea id="textarea-keywords" class="form-control" rows="10" placeholder="{{ __('Monitoring v2 create keywords ph') }}"></textarea>
                </div>
                <div class="form-group">
                    <label for="csv-keywords">{{ __('Monitoring v2 create csv label') }}</label>
                    <input type="file" id="csv-keywords" class="form-control form-control-sm" accept=".csv,text/csv">
                    <small class="text-muted d-block mt-1">{{ __('Monitoring v2 create csv help') }}</small>
                </div>
                <div class="form-group">
                    <label for="csv-delimiter">{{ __('Monitoring v2 create csv delimiter') }}</label>
                    <select class="form-select form-select-sm" id="csv-delimiter">
                        <option value=";">{{ __('Monitoring v2 create csv semi') }}</option>
                        <option value=",">{{ __('Monitoring v2 create csv comma') }}</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="relevant-url">{{ __('Monitoring v2 create relevant label') }}</label>
                    <input type="text" class="form-control form-control-sm" id="relevant-url" placeholder="https://example.com/page">
                </div>
                <div class="form-group">
                    <label>{{ __('Target') }}</label>
                    <select class="form-select form-select-sm" name="target">
                        <option value="1">1</option>
                        <option value="3">3</option>
                        <option value="5">5</option>
                        <option value="10" selected>10</option>
                        <option value="20">20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
                <div class="form-group">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="remove-duplicates" value="1" checked>
                        <label class="form-check-label" for="remove-duplicates">{{ __('Monitoring v2 create dedupe') }}</label>
                    </div>
                </div>
                <div class="form-group">
                    <label for="keyword-groups">{{ __('Group') }}</label>
                    <select class="form-control" id="keyword-groups" style="width: 100%;"></select>
                </div>
                <div class="input-group input-group-sm mb-0">
                    <input type="text" class="form-control" placeholder="{{ __('Monitoring v2 create new group ph') }}">
                    <button type="button" class="btn btn-outline-secondary" id="create-group">{{ __('Add') }}</button>
                </div>
            </div>
            <div class="card-footer">
                <button type="button" id="add-keywords" class="btn btn-primary w-100">
                    {{ __('Monitoring v2 create add keywords btn') }}
                </button>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card card-outline card-secondary">
            <table id="myTable" class="table table-striped table-sm w-100 mb-0"></table>
        </div>
    </div>
</div>
