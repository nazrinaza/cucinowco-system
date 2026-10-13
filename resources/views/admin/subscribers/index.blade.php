<x-layouts.admin title="Subscribers">
    <x-slot:actions>
        <a class="admin-button secondary" href="{{ route('admin.subscribers.export', request()->only(['q', 'status', 'segment'])) }}">Export CSV</a>
    </x-slot:actions>

    <form class="admin-filters" method="get">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Search name, email or phone">
        <select name="status">
            <option value="">All statuses</option>
            <option value="subscribed" @selected(request('status') === 'subscribed')>Subscribed</option>
            <option value="unsubscribed" @selected(request('status') === 'unsubscribed')>Unsubscribed</option>
            <option value="bounced" @selected(request('status') === 'bounced')>Bounced</option>
        </select>
        <select name="segment">
            <option value="">All segments</option>
            @foreach($segments as $segment)
                <option value="{{ $segment }}" @selected(request('segment') === $segment)>{{ $segment }}</option>
            @endforeach
        </select>
        <button type="submit">Filter</button>
        @if(request()->hasAny(['q', 'status', 'segment']))
            <a class="admin-button secondary" href="{{ route('admin.subscribers.index') }}">Reset</a>
        @endif
    </form>

    <div class="subscriber-tools-grid">
        <section class="admin-card">
            <div class="card-head"><div><p>Manual entry</p><h2>Add a subscriber</h2></div></div>
            <form class="admin-form" method="post" action="{{ route('admin.subscribers.store') }}">
                @csrf
                <label><span>Name</span><input name="name" value="{{ old('name') }}" maxlength="120" autocomplete="name" placeholder="Subscriber name"></label>
                <label><span>Email</span><input type="email" name="email" value="{{ old('email') }}" maxlength="160" autocomplete="email" placeholder="name@example.com" required></label>
                <label><span>Phone</span><input name="phone" value="{{ old('phone') }}" maxlength="30" autocomplete="tel" placeholder="+60 11-1234 5678"></label>
                <label><span>Status</span><select name="status" required><option value="subscribed" @selected(old('status', 'subscribed') === 'subscribed')>Subscribed</option><option value="unsubscribed" @selected(old('status') === 'unsubscribed')>Unsubscribed</option></select></label>
                <label><span>Segment</span><input name="segment" value="{{ old('segment') }}" list="subscriber-segments" maxlength="50" placeholder="e.g. Corporate"></label>
                <button class="admin-button" type="submit">Add subscriber</button>
            </form>
        </section>

        <section class="admin-card">
            <div class="card-head"><div><p>Import</p><h2>Add or update subscribers</h2></div></div>
            <form class="admin-form" method="post" action="{{ route('admin.subscribers.import') }}" enctype="multipart/form-data">
                @csrf
                <label><span>CSV file</span><input type="file" name="subscribers_csv" accept=".csv,text/csv,text/plain" required></label>
                <p class="muted">Maximum 2 MB / 5,000 rows. Required column: email. Optional: name, phone, status and segments. Separate multiple segments with |.</p>
                <button class="admin-button" type="submit">Import subscribers</button>
            </form>
        </section>

        <section class="admin-card">
            <div class="card-head"><div><p>Segments</p><h2>Available audience groups</h2></div></div>
            <div class="segment-library">
                @forelse($segments as $segment)
                    <a href="{{ route('admin.subscribers.index', ['segment' => $segment]) }}">{{ $segment }}</a>
                @empty
                    <p class="muted">Select subscribers below and add your first segment.</p>
                @endforelse
            </div>
        </section>
    </div>

    <form method="post" action="{{ route('admin.subscribers.segments') }}" data-subscriber-bulk-form>
        @csrf
        @method('patch')
        <section class="admin-card">
            <div class="subscriber-bulk-bar">
                <strong><span data-selected-count>0</span> selected</strong>
                <select name="segment_action" aria-label="Segment action">
                    <option value="add">Add to segment</option>
                    <option value="remove">Remove from segment</option>
                </select>
                <input name="segment" list="subscriber-segments" maxlength="50" placeholder="Segment name" required>
                <datalist id="subscriber-segments">
                    @foreach($segments as $segment)<option value="{{ $segment }}">@endforeach
                </datalist>
                <button class="admin-button" type="submit">Apply to selected</button>
                <button class="subscriber-bulk-delete" type="submit" formaction="{{ route('admin.subscribers.bulk-destroy') }}" formmethod="post" name="_method" value="delete" formnovalidate data-subscriber-bulk-delete>Delete selected</button>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th><input type="checkbox" data-select-all-subscribers aria-label="Select all subscribers on this page"></th><th>Subscriber</th><th>Segments</th><th>Status</th><th>Source</th><th>Joined</th><th>Action</th></tr></thead>
                    <tbody>
                    @forelse($subscribers as $subscriber)
                        <tr>
                            <td><input type="checkbox" name="subscriber_ids[]" value="{{ $subscriber->id }}" data-subscriber-checkbox aria-label="Select {{ $subscriber->email }}"></td>
                            <td><strong>{{ $subscriber->name ?: 'Newsletter subscriber' }}</strong><small>{{ $subscriber->email }}@if($subscriber->phone) &middot; {{ $subscriber->phone }}@endif</small></td>
                            <td><div class="subscriber-segments">@forelse($subscriber->segments ?? [] as $segment)<span>{{ $segment }}</span>@empty<small>Unsegmented</small>@endforelse</div></td>
                            <td><span class="status status-{{ $subscriber->status }}">{{ ucfirst($subscriber->status) }}</span></td>
                            <td>{{ str($subscriber->source)->replace('_', ' ')->title() }}</td>
                            <td>{{ $subscriber->subscribed_at?->format('d M Y') }}</td>
                            <td><button class="subscriber-delete-button" type="submit" form="delete-subscriber-{{ $subscriber->id }}" onclick="return confirm('Delete this subscriber permanently?')">Delete</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty-cell">No subscribers match this view.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </form>

    @foreach($subscribers as $subscriber)
        <form id="delete-subscriber-{{ $subscriber->id }}" method="post" action="{{ route('admin.subscribers.destroy', $subscriber) }}">@csrf @method('delete')</form>
    @endforeach

    <div class="pagination-wrap">{{ $subscribers->links() }}</div>
</x-layouts.admin>
