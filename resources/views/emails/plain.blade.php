{!! trim($plainText) !!}

--
{!! config('company.name') !!} by {!! config('company.legal_name') !!}
{!! config('company.address') !!}
Email: {!! config('company.email') !!}
WhatsApp: {!! route('whatsapp.redirect') !!}
