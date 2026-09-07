<x-layouts.admin title="Newsletter campaigns">
    <div class="detail-grid campaign-grid">
        <section class="admin-card">
            <div class="card-head">
                <div><p>Newsletter</p><h2>Campaign history</h2></div>
            </div>
            <div class="mini-list campaign-list">
                @forelse($campaigns as $campaign)
                    <article>
                        <span>
                            <strong>{{ $campaign->name }}</strong>
                            <small>{{ $campaign->subject }}</small>
                            @if($campaign->status === 'sent')
                                <small>{{ number_format($campaign->recipient_count) }} recipients &middot; {{ number_format($campaign->open_count) }} opens &middot; {{ number_format($campaign->click_count) }} clicks</small>
                            @elseif($campaign->scheduled_at)
                                <small>Scheduled {{ $campaign->scheduled_at->format('d M Y, g:i A') }}</small>
                            @endif
                            @if($campaign->delivery_error)<small class="campaign-error">{{ $campaign->delivery_error }}</small>@endif
                        </span>
                        <div class="campaign-actions">
                            <span class="status status-{{ $campaign->status }}">{{ ucfirst($campaign->status) }}</span>
                            @if(in_array($campaign->status, ['draft', 'scheduled', 'failed']))
                                <form method="post" action="{{ route('admin.campaigns.send', $campaign) }}">
                                    @csrf
                                    <button class="admin-button" type="submit">Send now</button>
                                </form>
                            @endif
                        </div>
                    </article>
                @empty
                    <p class="empty-cell">Create a draft campaign to begin.</p>
                @endforelse
            </div>
            <div class="pagination-wrap">{{ $campaigns->links() }}</div>
        </section>

        <section class="admin-card">
            <div class="card-head">
                <div><p>HTML builder</p><h2>New campaign</h2></div>
                <span class="editor-safe-badge">Sanitized HTML</span>
            </div>
            <form class="admin-form" method="post" action="{{ route('admin.campaigns.store') }}" data-newsletter-form>
                @csrf
                <label>
                    <span>Internal name</span>
                    <input name="name" value="{{ old('name') }}" required placeholder="September service reminder">
                </label>
                <label>
                    <span>Email subject</span>
                    <input name="subject" value="{{ old('subject') }}" required placeholder="A cleaner workplace starts here">
                </label>
                <label>
                    <span>Preview text</span>
                    <input name="preview_text" value="{{ old('preview_text') }}" placeholder="Short inbox preview shown after the subject">
                </label>

                @php($editorContent = app(\App\Support\NewsletterHtmlSanitizer::class)->sanitize(old('content', '')))
                <label>
                    <span>Message</span>
                    <div class="html-editor" data-html-editor>
                        <div class="html-editor-toolbar" role="toolbar" aria-label="Newsletter formatting">
                            <select data-editor-format aria-label="Text style">
                                <option value="p">Paragraph</option>
                                <option value="h2">Heading 2</option>
                                <option value="h3">Heading 3</option>
                            </select>
                            <span class="editor-divider" aria-hidden="true"></span>
                            <button type="button" data-editor-command="bold" title="Bold"><strong>B</strong></button>
                            <button type="button" data-editor-command="italic" title="Italic"><em>I</em></button>
                            <button type="button" data-editor-command="underline" title="Underline"><u>U</u></button>
                            <button type="button" data-editor-command="strikeThrough" title="Strikethrough"><s>S</s></button>
                            <span class="editor-divider" aria-hidden="true"></span>
                            <button type="button" data-editor-command="insertUnorderedList" title="Bullet list">&bull; List</button>
                            <button type="button" data-editor-command="insertOrderedList" title="Numbered list">1. List</button>
                            <button type="button" data-editor-link title="Insert link">Link</button>
                            <button type="button" data-editor-command="insertHorizontalRule" title="Divider line">Line</button>
                            <button type="button" data-editor-command="removeFormat" title="Clear formatting">Clear</button>
                            <button class="editor-source-toggle" type="button" data-editor-source-toggle title="Edit HTML source">&lt;/&gt; HTML</button>
                        </div>
                        <div
                            class="html-editor-canvas"
                            contenteditable="true"
                            role="textbox"
                            aria-multiline="true"
                            data-editor-canvas
                            data-placeholder="Write your newsletter message..."
                        ></div>
                        <textarea class="html-editor-source" name="content" rows="14" data-editor-source hidden>{{ $editorContent }}</textarea>
                        <div class="html-editor-foot">
                            <span>Use simple formatting for reliable display across email apps.</span>
                            <strong data-editor-count>0 characters</strong>
                        </div>
                    </div>
                </label>

                <label>
                    <span>Schedule <em>optional</em></span>
                    <input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at') }}">
                </label>
                <div class="campaign-submit-actions">
                    <button class="admin-button secondary" type="submit" name="action" value="draft">Save draft / schedule</button>
                    <button class="admin-button dark" type="submit" name="action" value="test">Send test email</button>
                    <button class="admin-button" type="submit" name="action" value="send">Save &amp; send now</button>
                </div>
                <p class="muted">Test email sends immediately to {{ auth()->user()->email }}. Bulk delivery is queued for the cPanel worker to protect the server from timeouts.</p>
            </form>
        </section>
    </div>
</x-layouts.admin>
