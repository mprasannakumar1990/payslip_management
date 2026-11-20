@extends('layouts.app')
@section('title', $employee->exists ? 'Edit Employee — '.$employee->name : 'Register Employee')
@section('back', $employee->exists ? route('employees.show', $employee) : route('employees.index'))
@section('content')
@php($v = fn ($f, $d = null) => old($f, $employee->$f ?? $d))
<form method="POST" action="{{ $employee->exists ? route('employees.update', $employee) : route('employees.store') }}" enctype="multipart/form-data">
    @csrf @if($employee->exists) @method('PUT') @endif
    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card mb-3">
                <div class="card-header">Personal &amp; employment details</div>
                <div class="card-body row g-3">
                    <div class="col-md-3"><label class="form-label">Emp ID *</label><input name="emp_code" class="form-control" value="{{ $v('emp_code') }}" required></div>
                    <div class="col-md-5"><label class="form-label">Full name *</label><input name="name" class="form-control" value="{{ $v('name') }}" required></div>
                    <div class="col-md-12"><label class="form-label">Other names used in Excel sheets (comma separated)</label><input name="name_aliases" class="form-control" value="{{ $v('name_aliases') }}" placeholder="e.g. Dharni, Prabharan (Old)"><div class="form-text">Used to match this employee automatically when importing OT / leave sheets.</div></div>
                    <div class="col-md-4"><label class="form-label">Designation</label><input name="designation" class="form-control" value="{{ $v('designation') }}" list="desig-list"></div>
                    <datalist id="desig-list"><option>Technical Service</option><option>Production</option><option>LAB and R&amp;D</option><option>Driver</option><option>Manager/Site Team</option><option>Manager/Ops Team</option><option>Executive/ Production Team</option></datalist>
                    <div class="col-md-3"><label class="form-label">Grade</label><input name="grade" class="form-control" value="{{ $v('grade') }}" list="grade-list"></div>
                    <datalist id="grade-list"><option>Emp</option><option>DM</option><option>SNR DM</option><option>MD</option></datalist>
                    <div class="col-md-3">
                        <label class="form-label">Plant / OT group</label>
                        <select name="ot_group" class="form-select">
                            <option value="">—</option>
                            @foreach ($groups as $g)<option @selected($v('ot_group') === $g)>{{ $g }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-3"><label class="form-label">Date of joining *</label><input type="date" name="date_of_joining" class="form-control" value="{{ old('date_of_joining', $employee->date_of_joining?->toDateString()) }}" required></div>
                    <div class="col-md-3"><label class="form-label">Date of exit</label><input type="date" name="date_of_exit" class="form-control" value="{{ old('date_of_exit', $employee->date_of_exit?->toDateString()) }}"></div>
                    <div class="col-md-3"><label class="form-label">Date of birth</label><input type="date" name="date_of_birth" class="form-control" value="{{ old('date_of_birth', $employee->date_of_birth?->toDateString()) }}"></div>
                    <div class="col-md-3">
                        <label class="form-label">Gender</label>
                        <select name="gender" class="form-select"><option value="">—</option>@foreach (['Male', 'Female', 'Other'] as $g)<option @selected($v('gender') === $g)>{{ $g }}</option>@endforeach</select>
                    </div>
                    <div class="col-md-3"><label class="form-label">Phone</label><input name="phone" class="form-control" value="{{ $v('phone') }}"></div>
                    <div class="col-md-3"><label class="form-label">Email (payslips are sent here)</label><input type="email" name="email" class="form-control" value="{{ $v('email') }}"></div>
                    <div class="col-12"><label class="form-label">Address</label><textarea name="address" rows="2" class="form-control">{{ $v('address') }}</textarea></div>
                    <div class="col-md-3">
                        <label class="form-label">Status *</label>
                        <select name="status" class="form-select">@foreach (['active', 'inactive', 'exited'] as $s)<option value="{{ $s }}" @selected($v('status') === $s)>{{ ucfirst($s) }}</option>@endforeach</select>
                    </div>
                </div>
            </div>
            <div class="card mb-3">
                <div class="card-header">Statutory &amp; bank</div>
                <div class="card-body row g-3">
                    <div class="col-md-4"><label class="form-label">EPF / UAN No</label><input name="uan_no" class="form-control" value="{{ $v('uan_no') }}"></div>
                    <div class="col-md-4"><label class="form-label">ESIC No</label><input name="esic_no" class="form-control" value="{{ $v('esic_no') }}"></div>
                    <div class="col-md-4"><label class="form-label">PAN</label><input name="pan_no" class="form-control" value="{{ $v('pan_no') }}"></div>
                    <div class="col-md-4"><label class="form-label">Bank name</label><input name="bank_name" class="form-control" value="{{ $v('bank_name') }}"></div>
                    <div class="col-md-4"><label class="form-label">Account no</label><input name="bank_account_no" class="form-control" value="{{ $v('bank_account_no') }}"></div>
                    <div class="col-md-4"><label class="form-label">IFSC</label><input name="bank_ifsc" class="form-control" value="{{ $v('bank_ifsc') }}"></div>
                </div>
            </div>
            <div class="card">
                <div class="card-header">Remarks</div>
                <div class="card-body"><textarea name="remarks" rows="2" class="form-control">{{ $v('remarks') }}</textarea></div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card mb-3">
                <div class="card-header">Photo</div>
                <div class="card-body d-flex align-items-center gap-3">
                    <label for="photoInput" class="photo-drop" id="photoDrop" title="Click or drop a photo">
                        @if ($employee->photo_path)
                            <img src="{{ $employee->photoUrl() }}" alt="" id="photoPreview">
                        @else
                            <img src="" alt="" id="photoPreview" class="d-none">
                            <span class="ph-empty" id="photoEmpty"><i class="bi bi-camera"></i><small>Add photo</small></span>
                        @endif
                    </label>
                    <div class="small">
                        <input type="file" name="photo" id="photoInput" accept="image/jpeg,image/png,image/webp" class="d-none">
                        <button type="button" class="btn btn-sm btn-outline-primary mb-1" onclick="document.getElementById('photoInput').click()"><i class="bi bi-upload"></i> Choose photo</button>
                        <div class="text-muted">JPG, PNG or WebP, up to 3 MB. Shown on the employee list, dashboard and birthday wishes.</div>
                        @if ($employee->photo_path)
                            <div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="remove_photo" value="1" id="removePhoto"><label class="form-check-label" for="removePhoto">Remove photo</label></div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="card mb-3">
                <div class="card-header">Salary structure (monthly)</div>
                <div class="card-body row g-3">
                    <div class="col-12"><label class="form-label">Basic salary *</label><input type="number" step="0.01" name="basic_salary" class="form-control" value="{{ $v('basic_salary', 0) }}" required><div class="form-text">Per day = Basic ÷ {{ num_clean(setting('working_days')) }}</div></div>
                    <div class="col-6"><label class="form-label">Manager special</label><input type="number" step="0.01" name="manager_special" class="form-control" value="{{ $v('manager_special', 0) }}"></div>
                    <div class="col-6"><label class="form-label">Factory special</label><input type="number" step="0.01" name="factory_special" class="form-control" value="{{ $v('factory_special', 0) }}"></div>
                    <div class="col-6"><label class="form-label">Monthly TAX (TDS)</label><input type="number" step="0.01" name="monthly_tax" class="form-control" value="{{ $v('monthly_tax', 0) }}"></div>
                    <div class="col-6"><label class="form-label">OT multiplier</label><input type="number" step="0.01" name="ot_multiplier" class="form-control" value="{{ $v('ot_multiplier', 1.5) }}" required></div>
                    <div class="col-12">
                        @foreach (['pf_applicable' => 'PF applicable (12%, max ₹'.num_clean(setting('pf_cap')).')', 'esi_applicable' => 'ESI applicable ('.num_clean(setting('esi_percent')).'% of gross)', 'ot_eligible' => 'Eligible for OT / shift allowance', 'attendance_bonus_applicable' => 'Attendance bonus (1 day pay when no absence)'] as $f => $label)
                            <div class="form-check">
                                <input type="hidden" name="{{ $f }}" value="0">
                                <input class="form-check-input" type="checkbox" name="{{ $f }}" value="1" id="{{ $f }}" @checked(old($f, $employee->$f))>
                                <label class="form-check-label" for="{{ $f }}">{{ $label }}</label>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header">Fixed allowances (monthly)</div>
                <div class="card-body row g-2">
                    @foreach ($allowanceTypes as $t)
                        <div class="col-6">
                            <label class="form-label small">{{ $t->name }} @unless($t->is_active)<span class="text-muted">(inactive)</span>@endunless</label>
                            <input type="number" step="0.01" min="0" name="allowances[{{ $t->id }}]" class="form-control form-control-sm" value="{{ old('allowances.'.$t->id, $amounts[$t->id] ?? '') }}">
                        </div>
                    @endforeach
                    <div class="form-text">Manage allowance types under Settings.</div>
                </div>
            </div>
        </div>
    </div>
    <div class="mt-3 d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-save"></i> {{ $employee->exists ? 'Save changes' : 'Register employee' }}</button>
        <a href="{{ $employee->exists ? route('employees.show', $employee) : route('employees.index') }}" class="btn btn-light">Cancel</a>
    </div>
</form>
@push('scripts')
<script>
(function () {
    const input = document.getElementById('photoInput'), drop = document.getElementById('photoDrop');
    const show = (file) => {
        if (!file || !/^image\//.test(file.type)) { window.notify?.({type: 'warning', title: 'Not an image', message: 'Choose a JPG, PNG or WebP photo.'}); return; }
        if (file.size > 3 * 1024 * 1024) { window.notify?.({type: 'warning', title: 'Photo too large', message: 'Please use a photo under 3 MB.'}); input.value = ''; return; }
        const img = document.getElementById('photoPreview');
        img.src = URL.createObjectURL(file); img.classList.remove('d-none');
        document.getElementById('photoEmpty')?.remove();
        drop.classList.add('pop');
        window.notify?.({type: 'success', title: 'Photo ready', message: file.name + ' — it is saved when you save the employee.', duration: 3500});
    };
    input.addEventListener('change', () => show(input.files[0]));
    ['dragenter', 'dragover'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.add('over'); }));
    ['dragleave', 'drop'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.remove('over'); }));
    drop.addEventListener('drop', e => { const f = e.dataTransfer.files[0]; if (f) { const dt = new DataTransfer(); dt.items.add(f); input.files = dt.files; show(f); } });
})();
</script>
@endpush
@if ($employee->exists)
    <form method="POST" action="{{ route('employees.destroy', $employee) }}" class="mt-3" data-confirm="Delete this employee? Employees with payslips will be marked inactive instead.">
        @csrf @method('DELETE')
        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Delete employee</button>
    </form>
@endif
@endsection
