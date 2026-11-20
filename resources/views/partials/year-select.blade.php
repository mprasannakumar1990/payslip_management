{{-- Year filter for an auto-filter form. Choosing a year here also switches the year for every other page. --}}
<select name="{{ $name ?? 'year' }}" class="form-select form-select-sm year-filter" style="width:{{ $width ?? '104px' }}" aria-label="Year" title="Year">
    @foreach (year_options() as $y)<option value="{{ $y }}" @selected($y == ($year ?? active_year()))>{{ $y }}</option>@endforeach
</select>
