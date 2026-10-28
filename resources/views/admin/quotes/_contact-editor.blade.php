@php($quotationContacts = old('contacts', $quote->contact_people ?? []))
<details class="admin-card quote-contact-editor" data-quote-contacts>
    <summary>Project contact persons<span>Add up to 10 contacts who should appear on this quotation.</span></summary>
    <form method="post" action="{{ route('admin.quotes.contacts.update', $quote) }}">
        @csrf @method('patch')
        <div class="quote-contact-list" data-quote-contact-list>
            @foreach($quotationContacts as $index => $contact)
                <div class="quote-contact-row" data-quote-contact-row>
                    <label><span>Name</span><input name="contacts[{{ $index }}][name]" value="{{ $contact['name'] ?? '' }}" maxlength="120" required></label>
                    <label><span>Role / designation</span><input name="contacts[{{ $index }}][role]" value="{{ $contact['role'] ?? '' }}" maxlength="120" placeholder="e.g. Site Manager"></label>
                    <label><span>Phone</span><input name="contacts[{{ $index }}][phone]" value="{{ $contact['phone'] ?? '' }}" maxlength="30" inputmode="tel"></label>
                    <label><span>Email</span><input type="email" name="contacts[{{ $index }}][email]" value="{{ $contact['email'] ?? '' }}" maxlength="160"></label>
                    <button type="button" class="admin-button secondary" data-remove-quote-contact>Remove</button>
                </div>
            @endforeach
        </div>
        <template data-quote-contact-template>
            <div class="quote-contact-row" data-quote-contact-row>
                <label><span>Name</span><input name="contacts[__INDEX__][name]" maxlength="120" required></label>
                <label><span>Role / designation</span><input name="contacts[__INDEX__][role]" maxlength="120" placeholder="e.g. Site Manager"></label>
                <label><span>Phone</span><input name="contacts[__INDEX__][phone]" maxlength="30" inputmode="tel"></label>
                <label><span>Email</span><input type="email" name="contacts[__INDEX__][email]" maxlength="160"></label>
                <button type="button" class="admin-button secondary" data-remove-quote-contact>Remove</button>
            </div>
        </template>
        <div class="quote-contact-actions">
            <button type="button" class="admin-button secondary" data-add-quote-contact>Add contact person</button>
            <button type="submit" class="admin-button">Save quotation contacts</button>
        </div>
        <p class="muted">Each contact must have at least a phone number or email address.</p>
    </form>
</details>
