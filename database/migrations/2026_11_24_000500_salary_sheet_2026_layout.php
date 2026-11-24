<?php

use App\Support\SalaryLayout;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Salary sheet layout from October 2026 ("Salary Workings Oct 2026.xls"):
 *  - two new fixed additions in gross: Other Allowance, General Allowances
 *  - a new deduction: Food Coupon (canteen, varies by month)
 *  - allowance columns: Spl Allowance, Food, Transport, Food Welfare, Mobile, Old Increment, Increment 2026
 *    ("Old Increment" = the former 2022 Increment + Pandemic Allowance + Increment 2024 + Increment 2025)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $t) {
            $t->decimal('other_allowance', 12, 2)->default(0)->after('factory_special');
            $t->decimal('general_allowance', 12, 2)->default(0)->after('other_allowance');
        });
        Schema::table('payslips', function (Blueprint $t) {
            $t->decimal('other_allowance', 12, 2)->default(0)->after('factory_special');
            $t->decimal('general_allowance', 12, 2)->default(0)->after('other_allowance');
            $t->decimal('food_coupon', 12, 2)->default(0)->after('advance');
        });
        SalaryLayout::applyAllowanceTypes();
    }

    public function down(): void
    {
        Schema::table('payslips', fn (Blueprint $t) => $t->dropColumn(['other_allowance', 'general_allowance', 'food_coupon']));
        Schema::table('employees', fn (Blueprint $t) => $t->dropColumn(['other_allowance', 'general_allowance']));
        DB::table('allowance_types')->whereIn('name', SalaryLayout::RETIRED)->update(['is_active' => true]);
    }
};
