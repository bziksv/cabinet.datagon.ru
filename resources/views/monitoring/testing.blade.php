@hasanyrole('Super Admin|admin')
<h5 class="mb-2 mt-4">Testing</h5>

{!! Form::open(['route' => ['keywords.set.test.positions', $project->id], 'method' => 'patch']) !!}

<input type="hidden" name="search" value="{{ request('region', $project->searchengines[0]->id) }}">

<div class="form-group">
    <label>[Year-month-day] Date range:</label>
    <div class="input-group">
        <span class="input-group-text">
            <i class="far fa-calendar-alt"></i>
        </span>
        <input type="text" name="date" class="form-control float-end" id="reservation">
        <button type="submit" class="btn btn-info btn-flat">Вставить позиции.</button>
    </div>
    <!-- /.input group -->
</div>
{!! Form::close() !!}
@endhasanyrole
