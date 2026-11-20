<?php

namespace Database\Seeders;

use App\Models\AllowanceType;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(['email' => 'superadmin@payslip.local'], [
            'name' => 'Super Admin',
            'password' => 'Admin@123',
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);
        User::updateOrCreate(['email' => 'admin@payslip.local'], [
            'name' => 'HR Admin',
            'password' => 'Admin@123',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        foreach ([
            ['EL', 'Earned Leave', 16, true, 1],
            ['CL', 'Casual Leave', 6, true, 2],
            ['SL', 'Sick Leave', 6, true, 3],
            ['LOP', 'Loss of Pay', 0, false, 4],
        ] as [$code, $name, $quota, $paid, $sort]) {
            LeaveType::updateOrCreate(['code' => $code], ['name' => $name, 'yearly_quota' => $quota, 'is_paid' => $paid, 'sort_order' => $sort]);
        }

        foreach (['Spl Allowance', 'Food', 'Transport', '2022 Increment', 'Mobile', 'Pandemic Allowance', 'Increment 2024', 'Increment 2025'] as $i => $name) {
            AllowanceType::updateOrCreate(['name' => $name], ['sort_order' => $i + 1, 'is_active' => true]);
        }

        // Employees, OT and leave history imported from the Excel workbooks in docs/sheets.
        $this->call(ExcelDataSeeder::class);
    }
}
