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
                            @if($campaign->recipient_count > 0 || in_array($campaign->status, ['sending', 'sent', 'failed']))
                                <a class="admin-button secondary" href="{{ route('admin.campaigns.analytics', $campaign) }}">Analytics</a>
                            @endif
                            @if(in_array($campaign->status, ['draft', 'scheduled', 'failed']))
                                <a class="admin-button secondary" href="{{ route('admin.campaigns.edit', $campaign) }}">Edit</a>
                                <form method="post" action="{{ route('admin.campaigns.send', $campaign) }}">
                                    @csrf
                                    <button class="admin-button" type="submit">Send now</button>
                                </form>
                            @endif
                            <form method="post" action="{{ route('admin.campaigns.duplicate', $campaign) }}">
                                @csrf
                                <button class="admin-button dark" type="submit">Duplicate</button>
                            </form>
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
                <div><p>HTML builder</p><h2>{{ isset($editingCampaign) ? 'Edit campaign' : 'New campaign' }}</h2></div>
                <span class="editor-safe-badge">Sanitized HTML</span>
            </div>
            @if(isset($editingCampaign))
                <div class="campaign-edit-notice">
                    <span>Editing <strong>{{ $editingCampaign->name }}</strong></span>
                    <a href="{{ route('admin.campaigns.index') }}">Cancel editing</a>
                </div>
            @endif
            <form class="admin-form" method="post" action="{{ isset($editingCampaign) ? route('admin.campaigns.update', $editingCampaign) : route('admin.campaigns.store') }}" data-newsletter-form>
                @csrf
                @if(isset($editingCampaign)) @method('patch') @endif
                <label>
                    <span>Internal name</span>
                    <input name="name" value="{{ old('name', $editingCampaign->name ?? '') }}" required placeholder="September service reminder">
                </label>
                <label>
                    <span>Email subject</span>
                    <input name="subject" value="{{ old('subject', $editingCampaign->subject ?? '') }}" required placeholder="A cleaner workplace starts here">
                </label>
                <label>
                    <span>Preview text</span>
                    <input name="preview_text" value="{{ old('preview_text', $editingCampaign->preview_text ?? '') }}" placeholder="Short inbox preview shown after the subject">
                </label>

                @php($editorContent = app(\App\Support\NewsletterHtmlSanitizer::class)->sanitize(old('content', $editingCampaign->content ?? '')))
                <label>
                    <span>Message</span>
                    <div class="html-editor" data-html-editor data-image-upload-url="{{ route('admin.campaigns.images.store') }}">
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
                            <button type="button" data-editor-image-button title="Upload and insert image">Image</button>
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
                        <input type="file" accept="image/jpeg,image/png,image/webp" data-editor-image-input hidden>
                        <div class="html-editor-foot">
                            <span data-editor-status>Images: JPG, PNG or WebP, up to 2 MB.</span>
                            <strong data-editor-count>0 characters</strong>
                        </div>
                    </div>
                </label>

                <label>
                    <span>Schedule <em>optional</em></span>
                    <input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at', isset($editingCampaign) && $editingCampaign->scheduled_at?->isFuture() ? $editingCampaign->scheduled_at->format('Y-m-d\\TH:i') : '') }}">
                </label>
                <div class="campaign-submit-actions">
                    <button class="admin-button secondary" type="submit" name="action" value="draft">{{ isset($editingCampaign) ? 'Update draft / schedule' : 'Save draft / schedule' }}</button>
                    <button class="admin-button dark" type="submit" name="action" value="test">Send test email</button>
                    <button class="admin-button" type="submit" name="action" value="send">{{ isset($editingCampaign) ? 'Update & send now' : 'Save & send now' }}</button>
                </div>
                <p class="muted">Test email sends immediately to {{ auth()->user()->email }}. Bulk delivery is queued for the cPanel worker to protect the server from timeouts.</p>
            </form>
        </section>
    </div>
</x-layouts.admin>
