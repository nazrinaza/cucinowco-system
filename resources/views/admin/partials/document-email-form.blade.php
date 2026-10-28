<section class="admin-card document-send-panel">
    <div><p>Email document</p><h2>Send {{ $documentLabel }} to any recipient</h2><small>Use commas, semicolons or new lines for multiple addresses. Maximum 10 recipients.</small></div>
    <form method="post" action="{{ $sendRoute }}">
        @csrf
        <label><span>Recipient email address(es)</span><textarea name="recipients" rows="2" placeholder="name@example.com" required>{{ old('recipients', $defaultEmail) }}</textarea></label>
        <button class="admin-button" type="submit">Send {{ $documentLabel }}</button>
    </form>
</section>
