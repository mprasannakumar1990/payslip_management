<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('emp_code', 30)->unique();
            $table->string('name');
            $table->string('designation')->nullable();
            $table->string('grade', 30)->nullable();
            $table->string('ot_group', 50)->nullable();
            $table->date('date_of_joining')->nullable();
            $table->date('date_of_exit')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 10)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('uan_no', 30)->nullable()->comment('EPF / UAN number');
            $table->string('esic_no', 30)->nullable();
            $table->string('pan_no', 20)->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_no', 40)->nullable();
            $table->string('bank_ifsc', 20)->nullable();
            $table->decimal('basic_salary', 12, 2)->default(0);
            $table->decimal('manager_special', 12, 2)->default(0);
            $table->decimal('factory_special', 12, 2)->default(0);
            $table->decimal('monthly_tax', 12, 2)->default(0);
            $table->decimal('ot_multiplier', 4, 2)->default(1.5);
            $table->boolean('pf_applicable')->default(true);
            $table->boolean('esi_applicable')->default(true);
            $table->boolean('ot_eligible')->default(true);
            $table->boolean('attendance_bonus_applicable')->default(true);
            $table->string('status', 20)->default('active'); // active, inactive, exited
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('allowance_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('employee_allowances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('allowance_type_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2)->default(0);
            $table->timestamps();
            $table->unique(['employee_id', 'allowance_type_id']);
        });

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->decimal('yearly_quota', 5, 2)->default(0);
            $table->boolean('is_paid')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained();
            $table->date('from_date');
            $table->date('to_date');
            $table->boolean('half_day')->default(false);
            $table->decimal('days', 5, 2);
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending'); // pending, approved, rejected, cancelled
            $table->text('admin_remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('actioned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('actioned_at')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'from_date']);
        });

        // Monthly leave usage recorded without specific dates (e.g. imported from the Excel register).
        Schema::create('leave_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('days', 5, 2);
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['employee_id', 'year', 'month']);
        });

        // Earned-leave days credited per year. A row here overrides the value computed from leave usage.
        Schema::create('el_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('days', 6, 2);
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'year']);
        });

        Schema::create('ot_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('second_shift_hours', 7, 2)->default(0);
            $table->decimal('third_shift_hours', 7, 2)->default(0);
            $table->decimal('normal_ot_hours', 7, 2)->default(0);
            $table->decimal('spl_ot_hours', 7, 2)->default(0);
            $table->string('remarks')->nullable();
            $table->string('status', 20)->default('pending'); // pending, approved, rejected
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'year', 'month']);
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('working_days', 5, 2)->default(26);
            $table->string('status', 20)->default('draft'); // draft, finalized
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
            $table->unique(['year', 'month']);
        });

        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            // employee snapshot at the time of the run
            $table->string('emp_code', 30);
            $table->string('name');
            $table->string('designation')->nullable();
            $table->string('grade', 30)->nullable();
            $table->string('ot_group', 50)->nullable();
            $table->string('uan_no', 30)->nullable();
            $table->string('esic_no', 30)->nullable();
            $table->date('date_of_joining')->nullable();
            // basic
            $table->decimal('basic_salary', 12, 2)->default(0);
            $table->decimal('per_day', 12, 4)->default(0);
            $table->decimal('working_days', 5, 2)->default(26);
            $table->decimal('present_days', 5, 2)->default(26);
            $table->decimal('absent_days', 5, 2)->default(0);
            $table->decimal('lop_days', 5, 2)->default(0);
            $table->decimal('basic_earned', 12, 2)->default(0);
            // additions
            $table->decimal('second_shift_hours', 7, 2)->default(0);
            $table->decimal('second_shift_amount', 12, 2)->default(0);
            $table->decimal('third_shift_hours', 7, 2)->default(0);
            $table->decimal('third_shift_amount', 12, 2)->default(0);
            $table->decimal('spl_ot_amount', 12, 2)->default(0);
            $table->decimal('ot_hours', 7, 2)->default(0);
            $table->decimal('ot_multiplier', 4, 2)->default(1.5);
            $table->decimal('ot_amount', 12, 2)->default(0);
            $table->decimal('ot_amount_manual', 12, 2)->nullable()->comment('when set, replaces the calculated OT amount');
            $table->decimal('manager_special', 12, 2)->default(0);
            $table->decimal('factory_special', 12, 2)->default(0);
            $table->decimal('total_addition', 12, 2)->default(0);
            $table->decimal('gross_salary', 12, 2)->default(0);
            // deductions
            $table->decimal('esi', 12, 2)->default(0);
            $table->decimal('pf', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('advance', 12, 2)->default(0);
            $table->decimal('other_deduction', 12, 2)->default(0);
            $table->decimal('total_deduction', 12, 2)->default(0);
            $table->decimal('net_salary', 12, 2)->default(0);
            // allowances
            $table->json('allowances')->nullable();
            $table->decimal('attendance_bonus', 12, 2)->default(0);
            $table->decimal('birthday_welfare', 12, 2)->default(0);
            $table->decimal('total_allowance', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->json('leave_summary')->nullable();
            $table->string('remarks')->nullable();
            $table->timestamps();
            $table->unique(['payroll_run_id', 'employee_id']);
        });

        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date_of_exit')->nullable();
            $table->date('settlement_date');
            $table->decimal('basic_salary', 12, 2)->default(0);
            $table->decimal('per_day_rate', 12, 2)->default(0);
            $table->json('el_rows')->nullable();
            $table->decimal('el_days', 7, 2)->default(0);
            $table->decimal('el_amount', 12, 2)->default(0);
            $table->decimal('gratuity_years', 5, 2)->default(0);
            $table->decimal('gratuity_amount', 12, 2)->default(0);
            $table->decimal('bonus_amount', 12, 2)->default(0);
            $table->decimal('other_amount', 12, 2)->default(0);
            $table->decimal('deduction_amount', 12, 2)->default(0);
            $table->decimal('round_off', 8, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 50);
            $table->string('description');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['activity_logs', 'settlements', 'payslips', 'payroll_runs', 'ot_entries', 'el_ledgers', 'leave_adjustments',
            'leave_requests', 'leave_types', 'employee_allowances', 'allowance_types', 'employees', 'settings'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
