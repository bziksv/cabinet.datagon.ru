<div class="table-responsive cabinet-tariff-summary">
    <table class="table table-sm align-middle mb-0" id="{{ $id }}">
        <tbody>
        @foreach($total as $t)
            <tr>
                <th class="text-secondary fw-normal">{{ $t['title'] }}</th>
                <td class="fw-semibold text-end">{{ $t['value'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
