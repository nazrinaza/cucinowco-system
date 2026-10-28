<x-layouts.admin title="Google Calendar" heading="Company calendar sync">
    <div class="calendar-integration-grid">
        <section class="admin-card calendar-connection-card">
            <div class="card-head"><div><p class="admin-kicker">Connection</p><h2>One shared company calendar</h2></div></div>
            <div class="calendar-connection-body">
                @if($connection)
                    <span class="calendar-sync-status calendar-sync-synced">Connected</span>
                    <dl>
                        <div><dt>Google account</dt><dd>{{ $connection->google_email ?: 'Connected account' }}</dd></div>
                        <div><dt>Calendar ID</dt><dd>{{ $connection->calendar_id }}</dd></div>
                        <div><dt>Connected by</dt><dd>{{ $connection->connectedBy?->name ?: 'Administrator' }}</dd></div>
                        <div><dt>Connected</dt><dd>{{ $connection->connected_at?->timezone('Asia/Kuala_Lumpur')->format('d M Y, g:i A') }}</dd></div>
                    </dl>
                    <div class="calendar-actions">
                        <form method="post" action="{{ route('admin.integrations.google-calendar.sync-upcoming') }}">
                            @csrf<button class="admin-button" type="submit">Sync upcoming appointments &amp; jobs</button>
                        </form>
                        <form method="post" action="{{ route('admin.integrations.google-calendar.disconnect') }}" onsubmit="return confirm('Disconnect Google Calendar? Existing Google events will remain unchanged.')">
                            @csrf<button class="admin-button secondary" type="submit">Disconnect</button>
                        </form>
                    </div>
                @else
                    <span class="calendar-sync-status calendar-sync-not-connected">Not connected</span>
                    <p>Connect the authorised company Google account. Connecting does not automatically sync existing bookings.</p>
                    <a class="admin-button" href="{{ route('admin.integrations.google-calendar.connect') }}">Connect Google Calendar</a>
                @endif
            </div>
        </section>

        <section class="admin-card calendar-sync-summary">
            <div class="card-head"><div><p class="admin-kicker">Sync health</p><h2>Booking events</h2></div></div>
            <div class="calendar-sync-metrics">
                @foreach(['synced', 'queued', 'failed', 'not_connected', 'deleted'] as $syncStatus)
                    <div><strong>{{ number_format(($statusCounts[$syncStatus] ?? 0) + ($siteVisitStatusCounts[$syncStatus] ?? 0)) }}</strong><span>{{ str($syncStatus)->replace('_', ' ')->title() }}</span></div>
                @endforeach
            </div>
            <p class="calendar-one-way-note"><strong>One-way sync:</strong> CuciNow is the source of truth. Editing an event in Google Calendar does not change the booking.</p>
        </section>
    </div>

    <section class="admin-card">
        <div class="card-head"><div><p class="admin-kicker">Recent activity</p><h2>Calendar sync history</h2></div></div>
        <div class="table-scroll"><table>
            <thead><tr><th>Type</th><th>Reference</th><th>Customer</th><th>Status</th><th>Last attempt</th><th>Message</th><th></th></tr></thead>
            <tbody>
            @forelse($recentCalendarSyncs as $sync)
                <tr>
                    <td>{{ $sync['type'] }}</td>
                    <td><strong>{{ $sync['reference'] }}</strong></td>
                    <td>{{ $sync['customer'] }}</td>
                    <td><span class="calendar-sync-status calendar-sync-{{ str($sync['status'])->replace('_', '-') }}">{{ str($sync['status'])->replace('_', ' ')->title() }}</span></td>
                    <td>{{ $sync['last_attempted_at']?->timezone('Asia/Kuala_Lumpur')->format('d M Y, g:i A') ?: '—' }}</td>
                    <td class="calendar-sync-message">{{ $sync['last_error'] ?: '—' }}</td>
                    <td>@if($sync['retry_route'])<form method="post" action="{{ $sync['retry_route'] }}">@csrf<button class="admin-button secondary" type="submit">Retry</button></form>@endif</td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty-cell">No calendar sync activity yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </section>
</x-layouts.admin>
