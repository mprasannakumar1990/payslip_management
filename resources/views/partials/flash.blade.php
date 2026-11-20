@foreach (['success' => 'success', 'error' => 'danger', 'warning' => 'warning'] as $key => $cls)
    @if (session($key))
        <div class="alert alert-{{ $cls }} alert-dismissible fade show no-print" role="alert">
            {{ session($key) }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
@endforeach
@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show no-print">
        <strong>Please fix the following:</strong>
        <ul class="mb-0">
            @foreach ($errors->all() as $e)
                <li>{{ $e }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
