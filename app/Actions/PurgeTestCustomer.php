<?php

namespace App\Actions;

use App\Models\AgentCommission;
use App\Models\Booking;
use App\Models\BookingCalendarSync;
use App\Models\Customer;
use App\Models\EmailEvent;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\ReferralSource;
use App\Models\SiteVisitCalendarSync;
use App\Models\SiteVisitPhoto;
use App\Models\SiteVisitRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class PurgeTestCustomer
{
    /** @return array<string, int|float> */
    public function summary(Customer $customer): array
    {
        $invoiceIds = $customer->invoices()->pluck('id');

        return [
            'site_visits' => $customer->siteVisitRequests()->count(),
            'photos' => SiteVisitPhoto::whereIn('site_visit_request_id', $customer->siteVisitRequests()->select('id'))->count(),
            'quotes' => $customer->quotes()->count(),
            'proformas' => $customer->proformaInvoices()->count(),
            'bookings' => $customer->bookings()->count(),
            'invoices' => $invoiceIds->count(),
            'payments' => DB::table('payments')->whereIn('invoice_id', $invoiceIds)->count(),
            'amount_paid' => (float) $customer->invoices()->sum('amount_paid'),
            'commissions' => AgentCommission::whereIn('invoice_id', $invoiceIds)->count(),
        ];
    }

    /** @return array<string, int|float> */
    public function handle(Customer $customer): array
    {
        $summary = $this->summary($customer);
        $photoPaths = [];

        DB::transaction(function () use ($customer, &$photoPaths): void {
            $lockedCustomer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            $siteVisitIds = $lockedCustomer->siteVisitRequests()->pluck('id');
            $quoteIds = $lockedCustomer->quotes()->pluck('id');
            $proformaIds = $lockedCustomer->proformaInvoices()->pluck('id');
            $bookingIds = $lockedCustomer->bookings()->pluck('id');
            $invoiceIds = $lockedCustomer->invoices()->pluck('id');

            $calendarEvents = BookingCalendarSync::whereIn('booking_id', $bookingIds)
                ->whereNotNull('google_event_id')->where('status', '!=', 'deleted')->count()
                + SiteVisitCalendarSync::whereIn('site_visit_request_id', $siteVisitIds)
                    ->whereNotNull('google_event_id')->where('status', '!=', 'deleted')->count();

            if ($calendarEvents > 0) {
                throw ValidationException::withMessages([
                    'customer' => 'This customer still has an active Google Calendar event. Cancel and sync the linked booking or site visit before deleting the test data.',
                ]);
            }

            $photoPaths = SiteVisitPhoto::whereIn('site_visit_request_id', $siteVisitIds)
                ->whereNotNull('path')->pluck('path')->all();
            SiteVisitPhoto::whereIn('site_visit_request_id', $siteVisitIds)->update(['before_photo_id' => null]);

            $this->deleteEmailHistory($quoteIds->all(), $invoiceIds->all(), $proformaIds->all());
            AgentCommission::whereIn('invoice_id', $invoiceIds)->delete();
            Invoice::whereIn('id', $invoiceIds)->delete();
            Booking::whereIn('id', $bookingIds)->delete();
            Quote::whereIn('id', $quoteIds)->delete();

            $referralSourceIds = SiteVisitRequest::whereIn('id', $siteVisitIds)
                ->whereNotNull('referral_source_id')->pluck('referral_source_id')->unique();
            SiteVisitRequest::whereIn('id', $siteVisitIds)->delete();

            foreach ($referralSourceIds as $sourceId) {
                ReferralSource::whereKey($sourceId)->update([
                    'lead_count' => SiteVisitRequest::where('referral_source_id', $sourceId)->count(),
                ]);
            }

            $lockedCustomer->delete();
        }, 3);

        if ($photoPaths !== []) {
            Storage::disk('local')->delete($photoPaths);
        }

        return $summary;
    }

    /** @param array<int, int> $quoteIds
     * @param  array<int, int>  $invoiceIds
     * @param  array<int, int>  $proformaIds
     */
    private function deleteEmailHistory(array $quoteIds, array $invoiceIds, array $proformaIds): void
    {
        $documentReferences = collect([
            'quote_id' => $quoteIds,
            'invoice_id' => $invoiceIds,
            'proforma_invoice_id' => $proformaIds,
        ])->flatMap(fn (array $ids, string $key) => collect($ids)->map(fn (int $id) => [$key, (string) $id]));

        if ($documentReferences->isEmpty()) {
            return;
        }

        $matchingEvents = EmailEvent::query()->where(function ($query) use ($documentReferences): void {
            foreach ($documentReferences as [$key, $id]) {
                $query->orWhere("metadata->{$key}", $id);
            }
        });
        $providerIds = (clone $matchingEvents)->whereNotNull('provider_email_id')->pluck('provider_email_id');

        EmailEvent::query()->where(function ($query) use ($documentReferences, $providerIds): void {
            foreach ($documentReferences as [$key, $id]) {
                $query->orWhere("metadata->{$key}", $id);
            }
            if ($providerIds->isNotEmpty()) {
                $query->orWhereIn('provider_email_id', $providerIds);
            }
        })->delete();
    }
}
