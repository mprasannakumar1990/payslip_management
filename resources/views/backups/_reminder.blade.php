{{--
    Daily backup reminder + "Back up now" dialog (Super Admin).
    At the configured time (default 16:00) a dialog asks to take today's backup; "Yes" creates a ZIP package and downloads it.
    The reminder is not shown when a manual backup was already taken today after the reminder time,
    and can be snoozed for 30 minutes or skipped for the day (remembered in this browser).
--}}
@php
    $remEnabled = setting('backup_reminder_enabled', '1') === '1';
    $remTime = setting('backup_reminder_time', '16:00');
    $lastManual = setting('backup_last_manual_at');
@endphp
<div class="modal fade" id="backupModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content confirm-modal overflow-hidden">
            <div class="text-white p-4 text-center" style="background: linear-gradient(135deg, #059669, #0891b2 60%, #6366f1);">
                <div class="mx-auto mb-2 d-grid" style="width:72px;height:72px;border-radius:50%;place-items:center;background:rgba(255,255,255,.18);font-size:2rem;animation: pulse 1.6s ease-in-out infinite">
                    <i class="bi bi-database-down"></i>
                </div>
                <div class="fw-bold fs-5" id="bkTitle">Daily database backup</div>
                <div class="small opacity-75" id="bkSub">It's {{ \Illuminate\Support\Carbon::createFromFormat('H:i', $remTime)->format('g:i A') }} — time to take today's backup.</div>
            </div>
            <div class="modal-body p-4">
                <p class="mb-2">Do you want to back up the database now?</p>
                <ul class="small text-muted mb-3 ps-3">
                    <li>A full copy of all data (employees, leave, OT, payslips, settings) is saved as a <strong>ZIP package</strong>.</li>
                    <li>The ZIP is also <strong>downloaded to this computer</strong> so you have your own copy.</li>
                    <li>Last backup: <strong>{{ setting('backup_last_at') ? \Illuminate\Support\Carbon::parse(setting('backup_last_at'))->format('d M Y, g:i A') : 'never' }}</strong></li>
                </ul>
                <form method="POST" action="{{ route('backups.store') }}" id="bkForm" data-no-confirm data-busy="Creating the backup ZIP…">
                    @csrf
                    <input type="hidden" name="download" value="1">
                    <input type="hidden" name="trigger" value="reminder" id="bkTrigger">
                    <button class="btn btn-success w-100 py-2 fw-semibold"><i class="bi bi-check2-circle"></i> Yes, back up &amp; download ZIP</button>
                </form>
                <div class="d-flex gap-2 mt-2" id="bkLater">
                    <button type="button" class="btn btn-light flex-fill" id="bkSnooze"><i class="bi bi-alarm"></i> Remind me in 30 min</button>
                    <button type="button" class="btn btn-light flex-fill" id="bkSkip"><i class="bi bi-calendar-x"></i> Skip today</button>
                </div>
                <button type="button" class="btn btn-light w-100 mt-2 d-none" data-bs-dismiss="modal" id="bkCancel">Cancel</button>
                <div class="text-center small text-muted mt-3">Reminder time can be changed in <a href="{{ route('backups.index') }}">Database Backups</a>.</div>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    const cfg = {
        enabled: {{ $remEnabled ? 'true' : 'false' }},
        time: @json($remTime),
        lastManual: @json($lastManual ? \Illuminate\Support\Carbon::parse($lastManual)->format('Y-m-d\TH:i:s') : null),
        serverNow: @json(now()->format('Y-m-d\TH:i:s')),
    };
    const el = document.getElementById('backupModal');
    const modal = new bootstrap.Modal(el);
    const skew = new Date(cfg.serverNow).getTime() - Date.now();     // use the server clock, not the PC clock
    const now = () => new Date(Date.now() + skew);
    const ymd = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    const store = { get: k => { try { return localStorage.getItem(k); } catch (e) { return null; } }, set: (k, v) => { try { localStorage.setItem(k, v); } catch (e) {} } };

    function open(mode) {
        const manual = mode === 'manual';
        document.getElementById('bkTrigger').value = manual ? 'manual' : 'reminder';
        document.getElementById('bkTitle').textContent = manual ? 'Back up the database' : 'Daily database backup';
        document.getElementById('bkSub').textContent = manual ? 'Take a full backup now and download it as a ZIP.' : document.getElementById('bkSub').dataset.text;
        document.getElementById('bkLater').classList.toggle('d-none', manual);
        document.getElementById('bkCancel').classList.toggle('d-none', !manual);
        modal.show();
    }
    document.getElementById('bkSub').dataset.text = document.getElementById('bkSub').textContent;
    window.openBackupDialog = () => open('manual');
    document.querySelectorAll('[data-backup-now]').forEach(b => b.addEventListener('click', e => { e.preventDefault(); open('manual'); }));

    document.getElementById('bkSnooze').addEventListener('click', () => {
        store.set('bkSnoozeUntil', String(now().getTime() + 30 * 60 * 1000));
        modal.hide();
        schedule();
    });
    document.getElementById('bkSkip').addEventListener('click', () => { store.set('bkSkipDay', ymd(now())); modal.hide(); });

    function dueAt() {
        const [h, m] = cfg.time.split(':').map(Number);
        const d = now(); d.setHours(h, m, 0, 0);
        return d;
    }
    function shouldShow() {
        if (!cfg.enabled || el.classList.contains('show')) return false;
        const t = now(), due = dueAt();
        if (t < due) return false;
        if (store.get('bkSkipDay') === ymd(t)) return false;
        if (Number(store.get('bkSnoozeUntil') || 0) > t.getTime()) return false;
        if (cfg.lastManual && new Date(cfg.lastManual) >= due) return false;   // already backed up after the reminder time today
        return true;
    }
    let timer;
    function schedule() {
        clearTimeout(timer);
        if (!cfg.enabled) return;
        if (shouldShow()) { open('reminder'); return; }
        const t = now(), due = dueAt();
        let wait = due - t;
        const snooze = Number(store.get('bkSnoozeUntil') || 0) - t.getTime();
        if (wait <= 0) wait = snooze > 0 ? snooze : 0;
        if (wait <= 0) { // done or skipped for today: check again after midnight
            const tomorrow = new Date(t); tomorrow.setDate(t.getDate() + 1); tomorrow.setHours(0, 0, 5, 0);
            wait = tomorrow - t;
        }
        timer = setTimeout(schedule, Math.min(wait + 1000, 2147000000));
    }
    // wait until the page loader has gone, so the dialog is not hidden behind it
    window.addEventListener('load', () => setTimeout(schedule, 900));
})();
</script>
