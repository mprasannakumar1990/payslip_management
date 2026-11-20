<?php

namespace Database\Seeders;

use App\Models\AllowanceType;
use App\Models\ElLedger;
use App\Models\Employee;
use App\Models\EmployeeAllowance;
use App\Models\LeaveAdjustment;
use App\Models\OtEntry;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Loads the data extracted from the three Excel workbooks (database/seeders/data/*.json):
 *  - employees + fixed allowances   (Salary Workings Nov 2025.xls)
 *  - OT hours for November 2025     (OT Sheet ... .xls, "Nov 2025")
 *  - monthly leave usage 2021-2025  (El Calculation 2025.xlsx, yearly sheets)
 *  - EL credited per year 2009-2020 (El Calculation 2025.xlsx, "2009 to 2020")
 */
class ExcelDataSeeder extends Seeder
{
    public function run(): void
    {
        $dir = database_path('seeders/data');
        $admin = User::where('role', User::ROLE_SUPER_ADMIN)->value('id');
        $types = AllowanceType::pluck('id', 'name');

        $codes = [];
        foreach (json_decode(file_get_contents("$dir/employees.json"), true) as $e) {
            $emp = Employee::updateOrCreate(['emp_code' => $e['code']], [
                'name' => $e['name'],
                'designation' => $e['designation'],
                'grade' => $e['grade'],
                'ot_group' => $e['ot_group'],
                'date_of_joining' => $e['doj'],
                'uan_no' => $e['uan'],
                'esic_no' => $e['esic'],
                'basic_salary' => $e['basic'],
                'manager_special' => $e['manager_special'],
                'factory_special' => $e['factory_special'],
                'monthly_tax' => $e['tax'],
                'ot_multiplier' => $e['ot_multiplier'],
                'pf_applicable' => $e['pf'],
                'esi_applicable' => $e['esi'],
                'ot_eligible' => $e['ot_group'] !== 'MANAGEMENT',
                'attendance_bonus_applicable' => $e['attendance_bonus'],
                'status' => 'active',
                'created_by' => $admin,
            ]);
            $codes[$e['code']] = $emp->id;
            foreach ($e['allowances'] as $name => $amount) {
                if ($amount > 0 && isset($types[$name])) {
                    EmployeeAllowance::updateOrCreate(
                        ['employee_id' => $emp->id, 'allowance_type_id' => $types[$name]],
                        ['amount' => $amount]
                    );
                }
            }
        }

        // spellings used in the OT / leave sheets
        foreach (['TSA007' => 'Prabharan (Old)', 'TSA070' => 'Dharni', 'TSA084' => 'Jayaprakash V'] as $code => $alias) {
            Employee::where('emp_code', $code)->update(['name_aliases' => $alias]);
        }

        foreach (json_decode(file_get_contents("$dir/ot_nov_2025.json"), true) as $o) {
            if (! isset($codes[$o['code']])) {
                continue;
            }
            OtEntry::updateOrCreate(['employee_id' => $codes[$o['code']], 'year' => 2025, 'month' => 11], [
                'second_shift_hours' => $o['second'],
                'third_shift_hours' => $o['third'],
                'normal_ot_hours' => $o['normal'],
                'spl_ot_hours' => $o['spl'],
                'remarks' => is_string($o['remarks']) ? $o['remarks'] : null,
                'status' => 'approved',
                'created_by' => $admin,
                'approved_by' => $admin,
                'approved_at' => now(),
            ]);
        }

        LeaveAdjustment::where('note', 'Imported from Excel register')->delete();
        foreach (json_decode(file_get_contents("$dir/leave_monthly.json"), true) as $l) {
            if (! isset($codes[$l['code']])) {
                continue;
            }
            LeaveAdjustment::create([
                'employee_id' => $codes[$l['code']],
                'year' => $l['year'],
                'month' => $l['month'],
                'days' => $l['days'],
                'note' => 'Imported from Excel register',
                'created_by' => $admin,
            ]);
        }

        foreach (json_decode(file_get_contents("$dir/el_ledger.json"), true) as $l) {
            if (! isset($codes[$l['code']])) {
                continue;
            }
            ElLedger::updateOrCreate(['employee_id' => $codes[$l['code']], 'year' => $l['year']], [
                'days' => $l['days'],
                'note' => 'Imported from Excel (2009 to 2020)',
            ]);
        }
    }
}
