<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->decimal('rate_percent', 5, 2)->default(0);
            $table->decimal('introducer_rate_percent', 5, 2)->default(0);
            // net_sales = invoice subtotal - discount (before tax); total = invoice grand total.
            $table->string('basis', 20)->default('net_sales');
            $table->boolean('is_default')->default(false)->index();
            $table->timestamps();
        });

        Schema::table('agents', function (Blueprint $table) {
            $table->foreignId('commission_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('commission_rate_override', 5, 2)->nullable();
        });

        Schema::create('agent_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->string('type', 20)->default('direct');
            $table->string('basis', 20);
            $table->decimal('basis_amount', 12, 2);
            $table->decimal('rate_percent', 5, 2);
            $table->decimal('amount', 12, 2);
            // Fraction of a sen cut off when rounding `amount` down; accumulates in the company rounding reserve.
            $table->decimal('rounding_remainder', 10, 6)->default(0);
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('payout_reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['invoice_id', 'type']);
            $table->index(['agent_id', 'status']);
        });

        // Rate starts at 0% so nothing is earned until HQ sets the programme rate.
        DB::table('commission_plans')->insert([
            'name' => 'Standard', 'rate_percent' => 0, 'introducer_rate_percent' => 0,
            'basis' => 'net_sales', 'is_default' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_commissions');
        Schema::table('agents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('commission_plan_id');
            $table->dropColumn('commission_rate_override');
        });
        Schema::dropIfExists('commission_plans');
    }
};
