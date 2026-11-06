<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('newsletter_campaigns', function (Blueprint $table) {
            $table->unsignedBigInteger('delivery_cursor')->default(0)->after('recipient_count');
            $table->unsignedBigInteger('delivery_max_subscriber_id')->default(0)->after('delivery_cursor');
            $table->unsignedInteger('delivery_batch_number')->default(0)->after('delivery_max_subscriber_id');
            $table->unsignedInteger('processed_count')->default(0)->after('delivery_batch_number');
            $table->timestamp('delivery_started_at')->nullable()->after('processed_count');
            $table->timestamp('next_batch_at')->nullable()->index()->after('delivery_started_at');
        });

        // A campaign interrupted under the old all-in-one sender cannot expose
        // reliable progress. Pause it for an explicit, idempotent resume.
        DB::table('newsletter_campaigns')
            ->whereIn('status', ['queued', 'sending'])
            ->update([
                'status' => 'failed',
                'delivery_error' => 'Delivery was paused during the safer batching upgrade. Review the campaign and resume sending.',
                'next_batch_at' => null,
            ]);
    }

    public function down(): void
    {
        Schema::table('newsletter_campaigns', function (Blueprint $table) {
            $table->dropIndex(['next_batch_at']);
            $table->dropColumn([
                'delivery_cursor',
                'delivery_max_subscriber_id',
                'delivery_batch_number',
                'processed_count',
                'delivery_started_at',
                'next_batch_at',
            ]);
        });
    }
};
