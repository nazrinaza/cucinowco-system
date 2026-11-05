<x-layouts.admin title="Campaign delivery" heading="Campaign delivery">
    <x-slot:actions>
        <div class="action-row">
            <a class="admin-button secondary" href="{{ route('admin.campaigns.index') }}">Back to campaigns</a>
            <form method="post" action="{{ route('admin.campaigns.duplicate', $campaign) }}">
                @csrf
                <button class="admin-button" type="submit">Duplicate campaign</button>
            </form>
        </div>
    </x-slot:actions>

    <section class="admin-card campaign-analytics-head">
        <div>
            <p class="admin-kicker">{{ $campaign->name }}</p>
            <h2>{{ $campaign->subject }}</h2>
            <small>{{ number_format($campaign->recipient_count) }} recipients &middot; {{ $campaign->sent_at ? 'Sent '.$campaign->sent_at->format('d M Y, g:i A') : 'Created '.$campaign->created_at->format('d M Y, g:i A') }}</small>
        </div>
        <span class="status status-{{ $campaign->status }}">{{ ucfirst($campaign->status) }}</span>
    </section>

    <section class="admin-card" style="margin-top:24px">
        <div class="card-head"><div><p>Server protection</p><h2>Campaign analytics are managed in Resend</h2></div></div>
        <p>CuciNow no longer stores newsletter delivery, bounce, complaint, open or click webhooks. This reduces database writes and concurrent PHP work on the shared hosting server.</p>
        <p class="muted">Quotation, invoice, booking, receipt and other operational email delivery events continue to be recorded in CuciNow.</p>
        <a class="admin-button" href="https://resend.com/emails" target="_blank" rel="noopener noreferrer">Open Resend email logs &nearr;</a>
    </section>
</x-layouts.admin>
