{{-- Validation summary tiles. Expects $summary --}}
<div class="row g-2 mb-3">
    @foreach ([
        ['Rows in file', $summary['total'], 'grad-3', 'bi-file-earmark-spreadsheet'],
        ['Ready (no issues)', $summary['ok'], 'grad-5', 'bi-check2-circle'],
        ['With warnings', $summary['warnings'], 'grad-2', 'bi-exclamation-triangle'],
        ['With errors (skipped)', $summary['errors'], 'grad-4', 'bi-x-octagon'],
        ['Will be imported', $summary['importable'], 'grad-1', 'bi-cloud-check'],
    ] as [$label, $n, $grad, $icon])
        <div class="col-6 col-md">
            <div class="card stat-card {{ $grad }} h-100">
                <div class="card-body d-flex align-items-center gap-2 py-2">
                    <div class="icon"><i class="bi {{ $icon }}"></i></div>
                    <div><div class="fs-4 fw-bold" @if($label === 'Will be imported') id="importCount" @endif>{{ $n }}</div><div class="small opacity-75">{{ $label }}</div></div>
                </div>
            </div>
        </div>
    @endforeach
</div>
