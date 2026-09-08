<x-layouts.admin title="Campaign analytics" heading="Campaign analytics">
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
            <small>
                {{ $campaign->sent_at ? 'Sent '.$campaign->sent_at->format('d M Y, g:i A') : 'Created '.$campaign->created_at->format('d M Y, g:i A') }}
            </small>
        </div>
        <span class="status status-{{ $campaign->status }}">{{ ucfirst($campaign->status) }}</span>
    </section>

    <div class="analytics-metric-grid">
        <article class="admin-card">
            <span>Recipients</span>
            <strong>{{ number_format($metrics['recipients']) }}</strong>
            <small>Campaign audience</small>
        </article>
        <article class="admin-card analytics-highlight">
            <span>Unique opens</span>
            <strong>{{ number_format($metrics['unique_opens']) }}</strong>
            <small>{{ number_format($metrics['open_rate'], 1) }}% open rate &middot; {{ number_format($metrics['total_opens']) }} total opens</small>
        </article>
        <article class="admin-card">
            <span>Unique clicks</span>
            <strong>{{ number_format($metrics['unique_clicks']) }}</strong>
            <small>{{ number_format($metrics['click_rate'], 1) }}% click rate &middot; {{ number_format($metrics['total_clicks']) }} total clicks</small>
        </article>
        <article class="admin-card analytics-danger">
            <span>Delivery issues</span>
            <strong>{{ number_format($metrics['issues']) }}</strong>
            <small>{{ number_format($metrics['issue_rate'], 1) }}% bounced, failed, blocked or complained</small>
        </article>
    </div>

    <div class="analytics-layout">
        <section class="admin-card analytics-chart-card">
            <div class="card-head">
                <div><p>Engagement graph</p><h2>First 14 days after sending</h2></div>
                <div class="analytics-legend" aria-label="Graph legend">
                    <span><i class="legend-open"></i> Opens</span>
                    <span><i class="legend-click"></i> Clicks</span>
                    <span><i class="legend-issue"></i> Issues</span>
                </div>
            </div>
            <div class="analytics-chart-scroll">
                <div class="analytics-chart" role="img" aria-label="Daily campaign opens, clicks and delivery issues for the first 14 days">
                    @foreach($timeline as $day)
                        @php($dayTotal = $day['opens'] + $day['clicks'] + $day['issues'])
                        <div class="analytics-chart-day" title="{{ $day['date']->format('d M') }}: {{ $day['opens'] }} opens, {{ $day['clicks'] }} clicks, {{ $day['issues'] }} issues">
                            <span class="analytics-chart-value">{{ $dayTotal ?: '' }}</span>
                            <div class="analytics-chart-bars" style="height: {{ $dayTotal ? max(5, round(($dayTotal / $timelineMaximum) * 100)) : 1 }}%">
                                @if($dayTotal)
                                    <i class="chart-open" style="flex-grow: {{ $day['opens'] }}"></i>
                                    <i class="chart-click" style="flex-grow: {{ $day['clicks'] }}"></i>
                                    <i class="chart-issue" style="flex-grow: {{ $day['issues'] }}"></i>
                                @endif
                            </div>
                            <time datetime="{{ $day['date']->format('Y-m-d') }}">{{ $day['date']->format('d M') }}</time>
                        </div>
                    @endforeach
                </div>
            </div>
            @if($events->isEmpty())
                <p class="analytics-empty">No Resend events have arrived for this campaign yet. Confirm that the Resend webhook and domain tracking are enabled.</p>
            @endif
        </section>

        <section class="admin-card analytics-health-card">
            <div class="card-head"><div><p>Deliverability</p><h2>Email health</h2></div></div>
            <div class="analytics-health-list">
                @foreach([
                    ['Delivered', $metrics['delivered'], 'delivery_rate', 'healthy'],
                    ['Unique opens', $metrics['unique_opens'], 'open_rate', 'open'],
                    ['Unique clicks', $metrics['unique_clicks'], 'click_rate', 'click'],
                    ['Bounced', $metrics['bounced'], null, 'issue'],
                    ['Failed', $metrics['failed'], null, 'issue'],
                    ['Blocked', $metrics['blocked'], null, 'blocked'],
                    ['Delayed', $metrics['delayed'], null, 'delayed'],
                ] as [$label, $count, $rateKey, $style])
                    @php($percentage = $rateKey ? $metrics[$rateKey] : round(($count / max($metrics['recipients'], 1)) * 100, 1))
                    <div>
                        <p><span>{{ $label }}</span><strong>{{ number_format($count) }} <small>{{ number_format($percentage, 1) }}%</small></strong></p>
                        <span class="analytics-progress"><i class="progress-{{ $style }}" style="width: {{ min(100, $percentage) }}%"></i></span>
                    </div>
                @endforeach
            </div>
        </section>
    </div>

    <section class="admin-card analytics-problems">
        <div class="card-head">
            <div><p>Resend feedback</p><h2>Failed, blocked and delayed deliveries</h2></div>
            <span>{{ number_format($problemEvents->count()) }} recent events</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Status</th><th>Recipient</th><th>Time</th></tr></thead>
                <tbody>
                    @forelse($problemEvents as $event)
                        <tr>
                            <td><span class="status status-{{ str($problemLabels[$event->event_type] ?? 'Issue')->slug('_') }}">{{ $problemLabels[$event->event_type] ?? $event->event_type }}</span></td>
                            <td>{{ $event->recipient ?: 'Not provided by Resend' }}</td>
                            <td>{{ $event->occurred_at?->format('d M Y, g:i A') ?? 'Time unavailable' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="empty-cell">No delivery problems recorded for this campaign.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <p class="analytics-footnote">Unique metrics count each Resend email ID once. Open tracking can be affected by image blocking and privacy features in email clients.</p>
</x-layouts.admin>
