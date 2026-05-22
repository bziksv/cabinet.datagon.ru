<div class="mb-3">
    <label class="form-label" for="dateType">{{ __('The day of the last online') }}</label>
    <div class="row g-2">
        <div class="col-md-6">
            <select name="dateType" id="dateType" class="form-select">
                <option value="all">{{ __('From registration until selected day') }}</option>
                <option value="only">{{ __('Only selected day') }}</option>
            </select>
        </div>
        <div class="col-md-6">
            <input class="form-control" type="datetime-local" name="lastOnline" id="lastOnline">
        </div>
    </div>
</div>

<div class="mb-3">
    <label class="form-label" for="fileType">{{ __('File Type') }}</label>
    <select name="fileType" id="fileType" class="form-select">
        <option value="xls">Excel</option>
        <option value="csv">CSV</option>
    </select>
</div>

<div class="mb-0">
    <label class="form-label" for="verify">{{ __('Type user') }}</label>
    <select name="verify" id="verify" class="form-select">
        <option value="verify">{{ __('Verified user') }}</option>
        <option value="noVerify">{{ __('No verified user') }}</option>
        <option value="all">{{ __('Any user') }}</option>
    </select>
</div>
