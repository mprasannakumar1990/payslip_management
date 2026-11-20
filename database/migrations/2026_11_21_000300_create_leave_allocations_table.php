<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Yearly leave entitlement per employee and leave type (CL / SL / EL ...), generated at the start of each year.
        Schema::create('leave_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('days', 6, 2);
            $table->boolean('is_manual')->default(false)->comment('edited by an admin; not overwritten by regeneration');
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['employee_id', 'year', 'leave_type_id']);
            $table->index('year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_allocations');
    }
};
