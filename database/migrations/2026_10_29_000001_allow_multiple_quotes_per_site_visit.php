<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->foreignId('site_visit_request_id')
                ->nullable()
                ->after('customer_id')
                ->constrained('site_visit_requests')
                ->nullOnDelete();
        });

        // Preserve every existing site-visit-to-quotation link. The legacy
        // site_visit_requests.quote_id column remains as a pointer to the most
        // recent quotation for backwards compatibility during deployment.
        DB::table('site_visit_requests')
            ->whereNotNull('quote_id')
            ->orderBy('id')
            ->select(['id', 'quote_id'])
            ->chunkById(200, function ($siteVisits): void {
                foreach ($siteVisits as $siteVisit) {
                    DB::table('quotes')
                        ->where('id', $siteVisit->quote_id)
                        ->whereNull('site_visit_request_id')
                        ->update(['site_visit_request_id' => $siteVisit->id]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('site_visit_request_id');
        });
    }
};
