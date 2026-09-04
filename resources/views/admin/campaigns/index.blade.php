<x-layouts.admin title="Campaigns">
    <div class="detail-grid">
        <section class="admin-card">
            <div class="card-head"><div><p>Newsletter</p><h2>Campaign history</h2></div></div>
            <div class="mini-list campaign-list">
                @forelse($campaigns as $campaign)
                    <article>
                        <span><strong>{{ $campaign->name }}</strong><small>{{ $campaign->subject }}</small>
                            @if($campaign->status==='sent')<small>{{ number_format($campaign->recipient_count) }} recipients &middot; {{ number_format($campaign->open_count) }} opens &middot; {{ number_format($campaign->click_count) }} clicks</small>
                            @elseif($campaign->scheduled_at)<small>Scheduled {{ $campaign->scheduled_at->format('d M Y, g:i A') }}</small>@endif
                            @if($campaign->delivery_error)<small class="campaign-error">{{ $campaign->delivery_error }}</small>@endif
                        </span>
                        <div class="campaign-actions"><span class="status status-{{ $campaign->status }}">{{ ucfirst($campaign->status) }}</span>@if(in_array($campaign->status,['draft','scheduled','failed']))<form method="post" action="{{ route('admin.campaigns.send',$campaign) }}">@csrf<button class="admin-button" type="submit">Send now</button></form>@endif</div>
                    </article>
                @empty
                    <p class="empty-cell">Create a draft campaign to begin.</p>
                @endforelse
            </div>
            <div class="pagination-wrap">{{ $campaigns->links() }}</div>
        </section>
        <aside class="admin-card">
            <div class="card-head"><div><p>Builder</p><h2>New campaign</h2></div></div>
            <form class="admin-form" method="post" action="{{ route('admin.campaigns.store') }}">@csrf<label><span>Internal name</span><input name="name" required placeholder="September service reminder"></label><label><span>Email subject</span><input name="subject" required></label><label><span>Preview text</span><input name="preview_text"></label><label><span>Message</span><textarea name="content" rows="8" required placeholder="Write the newsletter content..."></textarea></label><label><span>Schedule <em>optional</em></span><input type="datetime-local" name="scheduled_at"></label><button class="admin-button" type="submit">Save campaign</button><p class="muted">Campaigns are delivered through Resend. Scheduled campaigns are picked up by the cPanel cron job.</p></form>
        </aside>
    </div>
</x-layouts.admin>
