{{-- Year + month filter for an auto-filter form ($p = Carbon of the selected month). --}}
@include('partials.year-select', ['year' => $p->year])
<select name="month" class="form-select form-select-sm" style="width:140px" aria-label="Month" title="Month">
    @foreach (range(1, 12) as $m)<option value="{{ $m }}" @selected($m == $p->month)>{{ month_name($m) }}</option>@endforeach
</select>
