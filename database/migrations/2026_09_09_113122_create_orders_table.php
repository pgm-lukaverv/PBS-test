<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            // Deleting a user cleans up their orders too.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Amount in the user's local currency (see users.country_code), never in EUR.
            // decimal (not float) to avoid floating-point rounding errors with money.
            $table->decimal('amount', 10, 2);
            // When the order was placed ("Besteld" in the spec) — separate from created_at/updated_at,
            // which Laravel manages automatically for bookkeeping rather than business data.
            $table->timestamp('ordered_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
