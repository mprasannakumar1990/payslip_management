{{-- Sheet chooser for an uploaded workbook. Expects $sheets, $sheet, $route, $params --}}
@if (count($sheets) > 1)
    <form method="GET" action="{{ $route }}" class="d-flex gap-2 align-items-center auto-filter">
        @foreach ($params as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
        <span class="small text-nowrap">Sheet</span>
        <select name="sheet" class="form-select form-select-sm" style="min-width:160px">
            @foreach ($sheets as $s)<option @selected($s === $sheet)>{{ $s }}</option>@endforeach
        </select>
    </form>
@endif
