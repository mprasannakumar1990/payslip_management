@extends('layouts.app')
@section('title', 'Import Data (Excel)')
@section('content')
<div class="card mb-3 overflow-hidden">
    <div class="card-body d-flex flex-wrap gap-3 align-items-center" style="background: linear-gradient(120deg, #eef2ff, #fdf2f8);">
        <div class="flex-grow-1">
            <div class="fw-bold fs-5">Load data from Excel in three steps</div>
            <div class="text-muted small">Download the .xlsx template (or use your own workbook) → upload → verify the preview → confirm twice. Nothing is saved before you confirm.</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <span class="badge rounded-pill text-bg-primary px-3 py-2"><i class="bi bi-download"></i> 1. Template</span>
            <span class="badge rounded-pill text-bg-info px-3 py-2"><i class="bi bi-upload"></i> 2. Upload</span>
            <span class="badge rounded-pill text-bg-warning px-3 py-2"><i class="bi bi-search"></i> 3. Verify</span>
            <span class="badge rounded-pill text-bg-success px-3 py-2"><i class="bi bi-check2-all"></i> 4. Confirm</span>
        </div>
    </div>
</div>

<div class="row g-3">
    @foreach ([
        ['ot', 'Monthly OT sheet', 'bi-clock-history', 'grad-2', 'Shift hours and overtime for one month. Your OT workbook (one sheet per month, e.g. "Jan 2026") is accepted as it is — the matching month sheet is picked automatically.'],
        ['leave', 'Yearly leave register', 'bi-calendar2-week', 'grad-5', 'Leave days per employee per month for one year (Jan … Dec columns). Your "El Calculation" workbook with a sheet per year is accepted as it is.'],
        ['employees', 'Employees', 'bi-people', 'grad-1', 'New joiners, salary revisions, increments, email addresses and exits. Rows are matched by Emp ID: existing employees are updated, new IDs are created.'],
    ] as [$type, $title, $icon, $grad, $text])
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="{{ $grad }} text-white p-3 rounded-top d-flex align-items-center gap-3" style="border-radius:14px 14px 0 0">
                    <div class="fs-2"><i class="bi {{ $icon }}"></i></div>
                    <div><div class="fw-bold fs-6">{{ $title }}</div><div class="small opacity-75">.xlsx · .xls · .csv</div></div>
                </div>
                <div class="card-body d-flex flex-column">
                    <p class="small text-muted">{{ $text }}</p>

                    <div class="p-2 rounded-3 mb-3" style="background:#f8fafc">
                        <div class="small fw-semibold mb-2"><i class="bi bi-1-circle text-primary"></i> Download the Excel template</div>
                        @if ($type === 'ot')
                            <form method="GET" action="{{ route('imports.template', 'ot') }}" class="d-flex gap-2">
                                <input type="month" name="period" value="{{ $period->format('Y-m') }}" class="form-control form-control-sm" required>
                                <button class="btn btn-sm btn-success text-nowrap"><i class="bi bi-file-earmark-excel"></i> Template .xlsx</button>
                            </form>
                        @elseif ($type === 'leave')
                            <form method="GET" action="{{ route('imports.template', 'leave') }}" class="d-flex gap-2">
                                <select name="year" class="form-select form-select-sm">@foreach (year_options() as $y)<option @selected($y == $period->year)>{{ $y }}</option>@endforeach</select>
                                <button class="btn btn-sm btn-success text-nowrap"><i class="bi bi-file-earmark-excel"></i> Template .xlsx</button>
                            </form>
                        @else
                            <a href="{{ route('imports.template', 'employees') }}" class="btn btn-sm btn-success"><i class="bi bi-file-earmark-excel"></i> Employee list / template .xlsx</a>
                        @endif
                        <div class="small text-muted mt-1">Pre-filled with your employees, with drop-downs, number checks and an Instructions sheet.</div>
                    </div>

                    <form method="POST" action="{{ route('imports.'.$type.'.upload') }}" enctype="multipart/form-data" class="mt-auto" data-no-confirm data-busy="Reading and validating the file…">@csrf
                        <div class="small fw-semibold mb-2"><i class="bi bi-2-circle text-primary"></i> Upload the filled file</div>
                        @if ($type === 'ot')
                            <label class="form-label">Month *</label>
                            <input type="month" name="period" value="{{ $period->format('Y-m') }}" class="form-control mb-2" required>
                        @elseif ($type === 'leave')
                            <label class="form-label">Year *</label>
                            <select name="year" class="form-select mb-2">@foreach (year_options() as $y)<option @selected($y == $period->year)>{{ $y }}</option>@endforeach</select>
                        @endif
                        <label class="form-label">File *</label>
                        <label class="xl-drop mb-3 drop-{{ $type }}">
                            <input type="file" name="file" accept=".xlsx,.xls,.csv" required>
                            <span class="xl-ico"><i class="bi bi-file-earmark-spreadsheet"></i></span>
                            <span class="xl-text"><strong>Drop your Excel file here</strong><small>or click to choose · .xlsx .xls .csv</small></span>
                            <span class="xl-file"><i class="bi bi-file-earmark-excel-fill"></i> <span data-name></span> <small data-size></small></span>
                        </label>
                        <button class="btn btn-primary w-100"><i class="bi bi-search"></i> Upload &amp; verify</button>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
</div>
@endsection
@push('styles')
<style>
    .xl-drop { position: relative; display: flex; align-items: center; gap: .8rem; padding: 1rem; border-radius: 14px; cursor: pointer; border: 2px dashed var(--c, #7c3aed);
        background: repeating-linear-gradient(135deg, var(--cs, #faf5ff), var(--cs, #faf5ff) 12px, #fff 12px, #fff 24px); transition: transform .2s, box-shadow .2s, background .3s; }
    .xl-drop.drop-ot { --c: #ea580c; --cs: #fff7ed; } .xl-drop.drop-leave { --c: #059669; --cs: #ecfdf5; } .xl-drop.drop-employees { --c: #4f46e5; --cs: #eef2ff; }
    .xl-drop:hover, .xl-drop.over { transform: translateY(-2px) scale(1.01); box-shadow: 0 10px 24px rgba(0,0,0,.08); }
    .xl-drop.over { background: var(--cs); }
    .xl-drop input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
    .xl-ico { width: 48px; height: 48px; flex: 0 0 48px; border-radius: 14px; display: grid; place-items: center; font-size: 1.5rem; color: #fff; background: var(--c); box-shadow: 0 6px 14px rgba(0,0,0,.15); }
    .xl-drop.over .xl-ico { animation: bounce .6s ease infinite; }
    @keyframes bounce { 50% { transform: translateY(-5px); } }
    .xl-text { display: flex; flex-direction: column; line-height: 1.25; } .xl-text small { color: var(--muted); }
    .xl-file { display: none; font-weight: 600; color: var(--c); }
    .xl-drop.has-file { border-style: solid; background: var(--cs); }
    .xl-drop.has-file .xl-text { display: none; } .xl-drop.has-file .xl-file { display: block; animation: pop .35s cubic-bezier(.2,.9,.3,1.4); }
    .xl-drop.has-file .xl-ico { background: #16a34a; }
</style>
@endpush
@push('scripts')
<script>
document.querySelectorAll('.xl-drop').forEach(drop => {
    const input = drop.querySelector('input[type=file]');
    const show = () => {
        const f = input.files[0];
        drop.classList.toggle('has-file', !!f);
        if (!f) return;
        drop.querySelector('[data-name]').textContent = f.name;
        drop.querySelector('[data-size]').textContent = '(' + (f.size / 1024).toFixed(1) + ' KB)';
        const ok = /\.(xlsx|xls|csv)$/i.test(f.name);
        window.notify?.(ok ? {type: 'success', title: 'File ready', message: f.name + ' — click “Upload & verify”.', duration: 3500}
                           : {type: 'warning', title: 'Not an Excel file', message: 'Please choose a .xlsx, .xls or .csv file.'});
    };
    input.addEventListener('change', show);
    ['dragenter', 'dragover'].forEach(ev => drop.addEventListener(ev, () => drop.classList.add('over')));
    ['dragleave', 'drop'].forEach(ev => drop.addEventListener(ev, () => drop.classList.remove('over')));
    drop.addEventListener('drop', () => setTimeout(show, 0));
});
</script>
@endpush
