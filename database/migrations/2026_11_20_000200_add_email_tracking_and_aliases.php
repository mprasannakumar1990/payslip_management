<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            $table->timestamp('emailed_at')->nullable()->after('remarks');
            $table->string('emailed_to')->nullable()->after('emailed_at');
            $table->string('email_error', 500)->nullable()->after('emailed_to');
        });

        Schema::table('employees', function (Blueprint $table) {
            // other spellings used for this person in imported sheets, e.g. "Dharni", "Prabharan (Old)"
            $table->string('name_aliases', 500)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('payslips', fn (Blueprint $t) => $t->dropColumn(['emailed_at', 'emailed_to', 'email_error']));
        Schema::table('employees', fn (Blueprint $t) => $t->dropColumn('name_aliases'));
    }
};
