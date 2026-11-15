<x-layouts.admin title="Agent content" heading="HQ creative library">
    <section class="admin-card"><div class="card-head"><div><p>Give agents the right tools</p><h2>Publish marketing content</h2></div></div>
        <form method="post" action="{{ route('admin.agent-media.store') }}" enctype="multipart/form-data" class="admin-form">@csrf
            <div class="agent-form-row"><label><span>Title</span><input name="title" value="{{ old('title') }}" maxlength="150" required></label><label><span>Category</span><select name="category">@foreach(['social', 'poster', 'video', 'caption', 'guide'] as $category)<option value="{{ $category }}">{{ ucfirst($category) }}</option>@endforeach</select></label></div>
            <label><span>Folder</span><select name="folder_id"><option value="">Library (no folder)</option>@foreach($folderOptions as $id => $label)<option value="{{ $id }}" @selected((int) old('folder_id', $folderFilter) === $id)>{{ $label }}</option>@endforeach</select></label>
            <label><span>Description / usage notes</span><textarea name="description" rows="2">{{ old('description') }}</textarea></label><label><span>Caption</span><textarea name="caption" rows="4" placeholder="Book a free site visit: {ReferralLink}">{{ old('caption') }}</textarea><small>Use {ReferralLink} to insert each agent’s personal link. Text only; agents cannot publish or modify HQ content.</small></label>
            <label><span>Asset file <em>optional for caption-only content</em></span><input type="file" name="file" accept="image/jpeg,image/png,image/webp,application/pdf,video/mp4"><small>JPG, PNG, WebP, PDF or MP4. Maximum 10 MB. Files are private and require portal access.</small></label>
            <div class="agent-form-row"><label><span>Visibility</span><select name="is_published"><option value="0">Draft — HQ only</option><option value="1">Published — approved agents</option></select></label><label><span>Expires at <em>optional, Malaysia time</em></span><input type="datetime-local" name="expires_at"></label></div><button class="admin-button" type="submit">Save content</button>
        </form>
    </section>
    <section class="admin-card" style="margin-top:24px">
        <div class="card-head"><div><p>Organise like a drive</p><h2>Folders</h2></div></div>
        <p class="muted">Agents see folders that contain published content. Deleting a folder moves its content and subfolders up one level.</p>
        <form method="post" action="{{ route('admin.agent-media.folders.store') }}" class="admin-form media-folder-new">@csrf
            <label><span>New folder</span><input name="name" maxlength="80" placeholder="e.g. 01 Start here" required></label>
            <label><span>Inside</span><select name="parent_id"><option value="">Library (top level)</option>@foreach($folderOptions as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select></label>
            <button class="admin-button">Create folder</button>
        </form>
        <div class="media-folder-list">
            @forelse($folders as $f)
                <details>
                    <summary><strong>{{ $folderOptions[$f->id] ?? $f->name }}</strong> <small>{{ $f->media_count }} item(s) · {{ $f->children_count }} subfolder(s)</small> <a href="{{ route('admin.agent-media.index', ['folder' => $f->id]) }}">Show items</a></summary>
                    <form method="post" action="{{ route('admin.agent-media.folders.update', $f) }}" class="admin-form media-folder-new">@csrf @method('patch')
                        <label><span>Name</span><input name="name" value="{{ $f->name }}" maxlength="80" required></label>
                        <label><span>Inside</span><select name="parent_id"><option value="">Library (top level)</option>@foreach($folderOptions as $id => $label)@if($id !== $f->id)<option value="{{ $id }}" @selected($f->parent_id === $id)>{{ $label }}</option>@endif @endforeach</select></label>
                        <label><span>Order</span><input name="position" type="number" min="0" max="999" value="{{ $f->position }}"></label>
                        <button class="admin-button">Save</button>
                    </form>
                    <form method="post" action="{{ route('admin.agent-media.folders.destroy', $f) }}" onsubmit="return confirm('Delete this folder? Its content moves up one level.')">@csrf @method('delete')<button class="commission-link">Delete folder</button></form>
                </details>
            @empty
                <p class="empty-cell">No folders yet. Content without a folder shows at the top of the agents' library.</p>
            @endforelse
        </div>
    </section>
    <section class="admin-card" style="margin-top:24px"><div class="card-head"><div><p>Content management</p><h2>Library entries @if($folderFilter)<small>· {{ $folderOptions[$folderFilter] ?? '' }} · <a href="{{ route('admin.agent-media.index') }}">show all</a></small>@endif</h2></div></div><div class="agent-admin-media-list">@forelse($media as $item)
        <details><summary><strong>{{ $item->title }}</strong> · {{ $item->folder ? $folderOptions[$item->folder_id] ?? $item->folder->name : 'Library' }} · {{ ucfirst($item->category) }} · {{ $item->is_published ? 'Published' : 'Draft' }}@if($item->expires_at?->isPast()) · Expired @endif</summary>
            <form method="post" action="{{ route('admin.agent-media.update', $item) }}" class="admin-form">@csrf @method('patch')<label><span>Title</span><input name="title" value="{{ $item->title }}" required></label><label><span>Category</span><select name="category">@foreach(['social', 'poster', 'video', 'caption', 'guide'] as $category)<option value="{{ $category }}" @selected($item->category === $category)>{{ ucfirst($category) }}</option>@endforeach</select></label><label><span>Folder</span><select name="folder_id"><option value="">Library (no folder)</option>@foreach($folderOptions as $id => $label)<option value="{{ $id }}" @selected($item->folder_id === $id)>{{ $label }}</option>@endforeach</select></label><label><span>Description</span><textarea name="description">{{ $item->description }}</textarea></label><label><span>Caption</span><textarea name="caption" rows="4">{{ $item->caption }}</textarea></label><label><span>Visibility</span><select name="is_published"><option value="0" @selected(!$item->is_published)>Draft</option><option value="1" @selected($item->is_published)>Published</option></select></label><label><span>Expires at</span><input type="datetime-local" name="expires_at" value="{{ $item->expires_at?->format('Y-m-d\TH:i') }}"></label><div class="action-row"><button class="admin-button">Update content</button>@if($item->path)<a class="admin-button secondary" href="{{ route('admin.agent-media.download', $item) }}">Download {{ $item->original_name }}</a>@endif</div></form>
        </details>
    @empty<p class="empty-cell">Add the first HQ marketing asset above.</p>@endforelse</div></section><div class="pagination-wrap">{{ $media->links() }}</div>
</x-layouts.admin>
