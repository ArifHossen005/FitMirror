<?php

use App\Enums\ShiftStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Date + wall-clock times rather than two DATETIMEs, so a shift
            // reads identically to the roster a manager would write on
            // paper and an overnight shift is expressed by ends_at being
            // *earlier* than starts_at (see App\Models\Shift::endsAt()).
            $table->date('shift_date');
            $table->time('starts_at');
            $table->time('ends_at');

            $table->string('status')->default(ShiftStatus::Scheduled->value);
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'shift_date']);
            $table->index(['store_id', 'shift_date']);
            $table->index(['user_id', 'shift_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
