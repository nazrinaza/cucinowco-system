<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Repair staging databases created from an older version of the base schema
     * or left part-way through the first email delivery migration.
     */
    public function up(): void
    {
        $this->addTimestampIfMissing('quotes', 'sent_at');
        $this->addTimestampIfMissing('invoices', 'sent_at');
        $this->addTimestampIfMissing('invoices', 'last_reminder_sent_at');
        $this->addTimestampIfMissing('bookings', 'confirmation_sent_at');
        $this->addDateTimeIfMissing('newsletter_campaigns', 'sent_at');

        if (Schema::hasTable('newsletter_campaigns') && ! Schema::hasColumn('newsletter_campaigns', 'delivery_error')) {
            Schema::table('newsletter_campaigns', function (Blueprint $table) {
                $table->text('delivery_error')->nullable();
            });
        }

        if (! Schema::hasTable('email_events')) {
            Schema::create('email_events', function (Blueprint $table) {
                $table->id();
                $table->string('event_key', 64)->unique();
                $table->string('provider', 30)->default('resend');
                $table->string('event_type', 80)->index();
                $table->string('provider_email_id')->nullable()->index();
                $table->string('recipient')->nullable()->index();
                $table->json('metadata')->nullable();
                $table->timestamp('occurred_at')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // This is a forward-only schema repair. The owning migrations remove
        // these columns and tables when a full rollback is intentionally run.
    }

    private function addTimestampIfMissing(string $tableName, string $column): void
    {
        if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, $column)) {
            Schema::table($tableName, function (Blueprint $table) use ($column) {
                $table->timestamp($column)->nullable();
            });
        }
    }

    private function addDateTimeIfMissing(string $tableName, string $column): void
    {
        if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, $column)) {
            Schema::table($tableName, function (Blueprint $table) use ($column) {
                $table->dateTime($column)->nullable();
            });
        }
    }
};
