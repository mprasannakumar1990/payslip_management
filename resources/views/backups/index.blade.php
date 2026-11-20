@extends('layouts.app')
@section('title', 'Database Backups')
@section('content')
@php
    $healthy = $last && $last->gt(now()->subHours(26)) && $status !== 'failed';
@endphp
<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card stat-card {{ $healthy ? 'grad-5' : 'grad-4' }} h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="icon"><i class="bi {{ $healthy ? 'bi-shield-check' : 'bi-shield-exclamation' }}"></i></div>
                <div>
                    <div class="fw-bold fs-5">{{ $healthy ? 'Backups OK' : ($status === 'failed' ? 'Last backup failed' : 'Backup overdue') }}</div>
                    <div class="small opacity-75">Last successful: {{ $last ? $last->format('d M Y, h:i A').' ('.$last->diffForHumans().')' : 'never' }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card grad-1 h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="icon"><i class="bi bi-archive"></i></div>
                <div>
                    <div class="fw-bold fs-5">{{ count($files) }} backups</div>
                    <div class="small opacity-75">{{ \App\Services\BackupService::size(collect($files)->sum('size')) }} · kept {{ $s['backup_keep_days'] }} days</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card {{ $task && $task['installed'] ? 'grad-3' : 'grad-2' }} h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="icon"><i class="bi bi-alarm"></i></div>
                <div>
                    @if ($task && $task['installed'])
                        <div class="fw-bold fs-5">Daily schedule on</div>
                        <div class="small opacity-75">Next run: {{ $task['next'] ?? '—' }}</div>
                    @elseif ($task)
                        <div class="fw-bold fs-5">Schedule not installed</div>
                        <div class="small opacity-75">Backups still run on the first use each day</div>
                    @else
                        <div class="fw-bold fs-5">Server schedule</div>
                        <div class="small opacity-75">Uses php artisan schedule:run</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@if ($status === 'failed' && $error)
    <div class="alert alert-danger"><strong>Last backup attempt failed</strong>{{ $failedAt ? ' ('.\Illuminate\Support\Carbon::parse($failedAt)->format('d M Y H:i').')' : '' }}: {{ $error }}
        <div class="small mt-1">Check that MySQL is running in the XAMPP Control Panel, then click “Back up now”.</div></div>
@elseif ($error)
    <div class="alert alert-warning">{{ $error }}</div>
@endif

<div class="row g-3">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header d-flex flex-wrap gap-2 align-items-center">
                <span><i class="bi bi-database"></i> Backup files</span>
                <button type="button" class="btn btn-sm btn-success ms-auto" data-backup-now><i class="bi bi-database-down"></i> Back up now (ZIP)</button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-stack">
                    <thead><tr><th>Backup</th><th>Created</th><th class="num">Size</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    @forelse ($files as $f)
                        <tr>
                            <td><i class="bi {{ str_ends_with($f['name'], '.zip') ? 'bi-file-earmark-zip text-success' : 'bi-file-earmark-binary text-secondary' }}"></i> {{ $f['name'] }} @if($loop->first)<span class="badge bg-success">latest</span>@endif</td>
                            <td>{{ $f['at']->format('d M Y, h:i A') }} <span class="small text-muted">({{ $f['at']->diffForHumans() }})</span></td>
                            <td class="num">{{ \App\Services\BackupService::size($f['size']) }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('backups.download', $f['name']) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-download"></i> Download</a>
                                <form method="POST" action="{{ route('backups.destroy', $f['name']) }}" class="d-inline" data-confirm="Delete the backup {{ $f['name'] }}? It cannot be recovered." data-confirm-title="Delete backup" data-confirm-level="double">@csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">No backups yet. Click “Back up now”.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white small text-muted">Stored in <code>{{ $dir }}</code> (not reachable from the web). Each backup is a ZIP package with a full MySQL dump (.sql) of the whole database and a README with restore steps. Older backups may be .sql.gz.</div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-sliders"></i> Backup settings</div>
            <div class="card-body">
                <form method="POST" action="{{ route('backups.settings') }}">@csrf @method('PUT')
                    <div class="p-3 rounded-3 mb-3" style="background:linear-gradient(120deg,#ecfdf5,#eef2ff)">
                        <div class="form-check form-switch mb-2">
                            <input type="hidden" name="backup_reminder_enabled" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" name="backup_reminder_enabled" value="1" id="remOn" @checked(old('backup_reminder_enabled', $s['backup_reminder_enabled']) == '1')>
                            <label class="form-check-label fw-semibold" for="remOn"><i class="bi bi-alarm"></i> Daily backup reminder</label>
                        </div>
                        <label class="form-label">Reminder time</label>
                        <input type="time" name="backup_reminder_time" class="form-control @error('backup_reminder_time') is-invalid @enderror" value="{{ old('backup_reminder_time', $s['backup_reminder_time']) }}" required>
                        @error('backup_reminder_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Every day at this time a pop-up asks to back up; “Yes” creates a ZIP and downloads it. Shown to Super Admins while the app is open.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Keep backups for (days)</label>
                        <input type="number" min="1" max="3650" name="backup_keep_days" class="form-control" value="{{ old('backup_keep_days', $s['backup_keep_days']) }}" required>
                        <div class="form-text">Older files are deleted automatically (the newest 3 are always kept).</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Also copy each backup to (optional)</label>
                        <input name="backup_copy_path" class="form-control @error('backup_copy_path') is-invalid @enderror" value="{{ old('backup_copy_path', $s['backup_copy_path']) }}" placeholder="e.g. D:\Backups\Payslip  or a Google Drive / OneDrive folder">
                        @error('backup_copy_path')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">A second drive or a synced cloud folder protects you if this PC's disk fails.</div>
                    </div>
                    <button class="btn btn-primary w-100"><i class="bi bi-save"></i> Save</button>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><i class="bi bi-info-circle"></i> How daily backups run</div>
            <div class="card-body small">
                <ol class="ps-3 mb-2">
                    <li>Windows Task Scheduler runs the backup every night at 11:00 PM (“Payslip DB Backup”). If the PC was off, it runs as soon as the PC is on again.</li>
                    <li>Safety net: if no backup was made in the last 24 hours, the app makes one automatically the next time someone uses it.</li>
                    <li>Daily reminder at {{ \Illuminate\Support\Carbon::createFromFormat('H:i', $s['backup_reminder_time'])->format('g:i A') }}{{ $s['backup_reminder_enabled'] == '1' ? '' : ' (currently off)' }}: confirm “Yes” to create a ZIP backup and download it.</li>
                </ol>
                <div class="text-muted">Restoring a backup replaces all current data — ask your IT person, or see “Restore” in the README.</div>
                @if ($task && $task['installed'])
                    <div class="mt-2"><span class="badge bg-light text-dark border">Task: {{ $task['status'] ?? '' }} · last run {{ $task['last'] ?? '—' }}</span></div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
