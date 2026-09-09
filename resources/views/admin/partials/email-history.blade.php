<section class="admin-card document-email-history" id="email-history">
    <div class="card-head">
        <div><p>Communication log</p><h2>Email sending history</h2></div>
        <a href="{{ request()->fullUrl() }}#email-history">Refresh status</a>
    </div>
    <p class="email-history-help">Queued means waiting to send. Sent to provider means the mail service accepted the message. Delivered confirms acceptance by the recipient’s mail server, not that it was read. All times are Malaysia time (MYT, UTC+8).</p>
    <div class="table-wrap"><table>
        <thead><tr><th>Date &amp; time (MYT)</th><th>Email</th><th>Recipient</th><th>Status</th><th>Send reference</th></tr></thead>
        <tbody>
            @forelse($emailHistory as $emailEvent)
                @php
                    [$label, $tone, $explanation] = match($emailEvent->event_type) {
                        'app.queued' => ['Queued', 'partial', 'Waiting for the queue worker.'],
                        'app.sent' => ['Sent to provider', 'sent', 'Sending completed through the configured mail service. Delivery confirmation is separate.'],
                        'app.previewed' => ['Preview only', 'partial', 'A test/log mailer was used. No customer email was sent.'],
                        'app.failed' => ['Sending failed', 'rejected', 'Could not queue the email, or the send job exhausted its retries. Check the server log.'],
                        'email.sent' => ['Sent', 'sent', 'Resend confirms the send request succeeded.'],
                        'email.delivered' => ['Delivered', 'completed', 'Accepted by the recipient’s mail server.'],
                        'email.opened' => ['Opened', 'completed', 'An open was detected; privacy features may affect open tracking.'],
                        'email.clicked' => ['Link clicked', 'completed', 'A link click was detected.'],
                        'email.delivery_delayed' => ['Delivery delayed', 'partial', 'The provider is retrying delivery.'],
                        'email.bounced' => ['Bounced', 'rejected', 'The recipient’s mail server rejected the message.'],
                        'email.suppressed' => ['Blocked', 'rejected', 'Resend suppressed this message.'],
                        'email.complained' => ['Spam complaint', 'rejected', 'The recipient reported this message as spam.'],
                        'email.failed' => ['Delivery failed', 'rejected', 'Resend reported a delivery failure.'],
                        default => [str($emailEvent->event_type)->replace(['email.', 'app.', '_'], ['', '', ' '])->title(), 'draft', 'Provider activity.'],
                    };
                    $metadata = $emailEvent->metadata ?? [];
                    $sendReference = $metadata['delivery_attempt_id'] ?? $emailEvent->provider_email_id;
                    $time = $emailEvent->occurred_at?->timezone('Asia/Kuala_Lumpur');
                    $category = match($metadata['category'] ?? '') {
                        'quotation' => 'Quotation', 'invoice' => 'Invoice',
                        'invoice_reminder' => 'Payment reminder', 'payment_receipt' => 'Payment receipt',
                        default => 'Email update',
                    };
                @endphp
                <tr>
                    <td>@if($time)<time datetime="{{ $time->toIso8601String() }}">{{ $time->format('d M Y') }}<small>{{ $time->format('h:i:s A') }}</small></time>@else Time unavailable @endif</td>
                    <td>{{ $category }}@if($emailEvent->event_type === 'app.queued')<small>Requested by {{ $metadata['requested_by'] ?? 'System' }}</small>@endif</td>
                    <td class="email-history-recipient">{{ $emailEvent->recipient ?? 'Not supplied' }}</td>
                    <td><span class="status status-{{ $tone }}">{{ $label }}</span><small>{{ $explanation }}</small></td>
                    <td><span title="{{ $sendReference }}">{{ $sendReference ? substr($sendReference, 0, 8) : 'Legacy' }}</span><small>{{ ucfirst($emailEvent->provider) }}</small></td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty-cell">No tracked email activity for this document yet. Older sends may not have a complete history.</td></tr>
            @endforelse
        </tbody>
    </table></div>
    @if($emailHistory->isEmpty() && $document->sent_at)
        <p class="email-history-help">Older send/status record: {{ $document->sent_at->timezone('Asia/Kuala_Lumpur')->format('d M Y, h:i:s A') }} MYT. This record alone does not verify email delivery.</p>
    @endif
    <p class="email-history-help">Delivery, bounce and open updates require Resend webhook reporting. Each resend has its own send reference. Refresh to see new activity.</p>
    @if($emailHistory->hasPages())<div class="email-history-pagination">{{ $emailHistory->links() }}</div>@endif
</section>
