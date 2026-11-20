<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\WorkflowController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\OtController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\PayslipController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SettlementController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.attempt');
});

Route::middleware(['auth', 'role'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/workflow', [WorkflowController::class, 'index'])->name('workflow.index');

    // ---- Excel / CSV imports ----
    Route::get('imports', [ImportController::class, 'index'])->name('imports.index');
    Route::get('imports/template/{type}', [ImportController::class, 'template'])->name('imports.template');
    Route::post('imports/ot/upload', [ImportController::class, 'otUpload'])->name('imports.ot.upload');
    Route::get('imports/ot/preview', [ImportController::class, 'otPreview'])->name('imports.ot.preview');
    Route::post('imports/ot', [ImportController::class, 'otStore'])->name('imports.ot.store');
    Route::post('imports/leave/upload', [ImportController::class, 'leaveUpload'])->name('imports.leave.upload');
    Route::get('imports/leave/preview', [ImportController::class, 'leavePreview'])->name('imports.leave.preview');
    Route::post('imports/leave', [ImportController::class, 'leaveStore'])->name('imports.leave.store');
    Route::post('imports/employees/upload', [ImportController::class, 'employeesUpload'])->name('imports.employees.upload');
    Route::post('imports/employees', [ImportController::class, 'employeesStore'])->name('imports.employees.store');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'password'])->name('profile.password');

    // ---- Employee registration ----
    Route::get('employees/export', [EmployeeController::class, 'export'])->name('employees.export');
    Route::resource('employees', EmployeeController::class);
    Route::post('employees/{employee}/el-ledger', [LeaveController::class, 'saveElLedger'])->name('employees.el-ledger');

    // ---- Leave management ----
    Route::post('leave-requests/{leave_request}/approve', [LeaveRequestController::class, 'approve'])->name('leave-requests.approve');
    Route::post('leave-requests/{leave_request}/reject', [LeaveRequestController::class, 'reject'])->name('leave-requests.reject');
    Route::post('leave-requests/{leave_request}/cancel', [LeaveRequestController::class, 'cancel'])->name('leave-requests.cancel');
    Route::get('leave-requests/calc-days', [LeaveRequestController::class, 'calcDays'])->name('leave-requests.calc-days');
    Route::resource('leave-requests', LeaveRequestController::class)->except('show');
    Route::get('leave/balances', [LeaveController::class, 'balances'])->name('leave.balances');
    Route::get('leave/register', [LeaveController::class, 'register'])->name('leave.register');
    Route::get('leave/el-summary', [LeaveController::class, 'elSummary'])->name('leave.el-summary');
    Route::post('leave/close-year', [LeaveController::class, 'closeYear'])->name('leave.close-year');
    Route::get('leave/adjustments', [LeaveController::class, 'adjustments'])->name('leave.adjustments');
    Route::post('leave/adjustments', [LeaveController::class, 'storeAdjustment'])->name('leave.adjustments.store');
    Route::delete('leave/adjustments/{adjustment}', [LeaveController::class, 'destroyAdjustment'])->name('leave.adjustments.destroy');

    // ---- OT management ----
    Route::get('ot', [OtController::class, 'index'])->name('ot.index');
    Route::post('ot', [OtController::class, 'save'])->name('ot.save');
    Route::post('ot/approve-month', [OtController::class, 'approveMonth'])->name('ot.approve-month');
    Route::post('ot/{entry}/status', [OtController::class, 'setStatus'])->name('ot.status');
    Route::get('ot/report', [OtController::class, 'report'])->name('ot.report');
    Route::get('ot/history', [OtController::class, 'history'])->name('ot.history');

    // ---- Payslip management ----
    Route::get('payroll', [PayrollController::class, 'index'])->name('payroll.index');
    Route::post('payroll', [PayrollController::class, 'store'])->name('payroll.store');
    Route::get('payroll/{run}', [PayrollController::class, 'show'])->name('payroll.show');
    Route::post('payroll/{run}/generate', [PayrollController::class, 'generate'])->name('payroll.generate');
    Route::post('payroll/{run}/finalize', [PayrollController::class, 'finalize'])->name('payroll.finalize');
    Route::get('payroll/{run}/export', [PayrollController::class, 'export'])->name('payroll.export');
    Route::get('payroll/{run}/pdf', [PayrollController::class, 'pdf'])->name('payroll.pdf');
    Route::post('payroll/{run}/email', [PayrollController::class, 'email'])->name('payroll.email');
    Route::get('payslips/{payslip}', [PayslipController::class, 'show'])->name('payslips.show');
    Route::get('payslips/{payslip}/edit', [PayslipController::class, 'edit'])->name('payslips.edit');
    Route::put('payslips/{payslip}', [PayslipController::class, 'update'])->name('payslips.update');
    Route::get('payslips/{payslip}/pdf', [PayslipController::class, 'pdf'])->name('payslips.pdf');
    Route::post('payslips/{payslip}/email', [PayslipController::class, 'email'])->name('payslips.email');

    // ---- EL / Gratuity final settlement ----
    Route::resource('settlements', SettlementController::class)->except(['edit', 'update']);
    Route::get('settlements/{settlement}/pdf', [SettlementController::class, 'pdf'])->name('settlements.pdf');

    // ---- Super admin only ----
    Route::middleware('role:super_admin')->group(function () {
        Route::resource('users', UserController::class)->except('show');
        Route::post('users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');
        Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
        Route::put('settings/mail', [SettingController::class, 'updateMail'])->name('settings.mail');
        Route::post('settings/mail/test', [SettingController::class, 'testMail'])->name('settings.mail.test');
        Route::post('settings/allowance-types', [SettingController::class, 'storeAllowanceType'])->name('settings.allowance-types.store');
        Route::put('settings/allowance-types/{type}', [SettingController::class, 'updateAllowanceType'])->name('settings.allowance-types.update');
        Route::put('settings/leave-types/{type}', [SettingController::class, 'updateLeaveType'])->name('settings.leave-types.update');
        Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
        Route::get('backups', [\App\Http\Controllers\BackupController::class, 'index'])->name('backups.index');
        Route::post('backups', [\App\Http\Controllers\BackupController::class, 'store'])->name('backups.store');
        Route::put('backups/settings', [\App\Http\Controllers\BackupController::class, 'settings'])->name('backups.settings');
        Route::get('backups/{name}/download', [\App\Http\Controllers\BackupController::class, 'download'])->name('backups.download')->where('name', '[A-Za-z0-9_.\-]+');
        Route::delete('backups/{name}', [\App\Http\Controllers\BackupController::class, 'destroy'])->name('backups.destroy')->where('name', '[A-Za-z0-9_.\-]+');
        Route::post('payroll/{run}/reopen', [PayrollController::class, 'reopen'])->name('payroll.reopen');
        Route::delete('payroll/{run}', [PayrollController::class, 'destroy'])->name('payroll.destroy');
    });
});
