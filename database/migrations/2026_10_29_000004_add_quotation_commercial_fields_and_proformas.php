<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('sales_signature_name')->nullable()->after('phone');
            $table->string('sales_signature_title', 160)->nullable()->after('sales_signature_name');
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->string('title')->nullable()->after('quote_number');
            $table->date('document_date')->nullable()->after('title');
            $table->decimal('deposit_percentage', 5, 2)->default(50)->after('total');
            $table->foreignId('sales_person_user_id')->nullable()->after('customer_id')->constrained('users')->nullOnDelete();
            $table->string('sales_person_name')->nullable()->after('sales_person_user_id');
            $table->string('sales_person_title', 160)->nullable()->after('sales_person_name');
        });

        Schema::create('proforma_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('proforma_number', 40)->unique();
            $table->foreignId('quote_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('title')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->date('issued_at');
            $table->date('valid_until')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('deposit_percentage', 5, 2)->default(50);
            $table->string('sales_person_name')->nullable();
            $table->string('sales_person_title', 160)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('last_edited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('proforma_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proforma_invoice_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('unit', 30)->default('job');
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proforma_invoice_items');
        Schema::dropIfExists('proforma_invoices');

        Schema::table('quotes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sales_person_user_id');
            $table->dropColumn(['title', 'document_date', 'deposit_percentage', 'sales_person_name', 'sales_person_title']);
        });

        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['sales_signature_name', 'sales_signature_title']));
    }
};
