<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoices') && ! Schema::hasColumn('invoices', 'sent_at')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->timestamp('sent_at')->nullable()->after('due_at');
            });
        }

        if (Schema::hasTable('invoices') && ! Schema::hasColumn('invoices', 'last_reminder_sent_at')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->timestamp('last_reminder_sent_at')->nullable()->after('sent_at');
            });
        }

        if (Schema::hasTable('bookings') && ! Schema::hasColumn('bookings', 'confirmation_sent_at')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->timestamp('confirmation_sent_at')->nullable()->after('scheduled_end');
            });
        }

        if (Schema::hasTable('newsletter_campaigns') && ! Schema::hasColumn('newsletter_campaigns', 'delivery_error')) {
            Schema::table('newsletter_campaigns', function (Blueprint $table) {
                $table->text('delivery_error')->nullable()->after('unsubscribe_count');
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
        Schema::dropIfExists('email_events');

        if (Schema::hasTable('newsletter_campaigns') && Schema::hasColumn('newsletter_campaigns', 'delivery_error')) {
            Schema::table('newsletter_campaigns', function (Blueprint $table) {
                $table->dropColumn('delivery_error');
            });
        }

        if (Schema::hasTable('bookings') && Schema::hasColumn('bookings', 'confirmation_sent_at')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropColumn('confirmation_sent_at');
            });
        }

        if (Schema::hasTable('invoices')) {
            $columns = array_values(array_filter(
                ['sent_at', 'last_reminder_sent_at'],
                fn (string $column): bool => Schema::hasColumn('invoices', $column),
            ));

            if ($columns !== []) {
                Schema::table('invoices', function (Blueprint $table) use ($columns) {
                    $table->dropColumn($columns);
                });
            }
        }
    }
};
