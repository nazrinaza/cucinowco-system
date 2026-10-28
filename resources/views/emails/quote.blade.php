@extends('emails.layout')
@section('title', 'Quotation '.$quote->quote_number)
@section('preview', 'Your CuciNow quotation is ready for review.')
@section('content')
    <p style="margin:0 0 10px;color:#8c6906;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.08em">Quotation</p>
    <h1 style="margin:0 0 8px;font-size:30px">{{ $quote->quote_number }}</h1>
    <p style="margin:0 0 24px;color:#73777d;font-size:13px">Prepared for {{ $quote->customer->name }} &middot; Valid until {{ $quote->valid_until?->format('d M Y') }}</p>
    <p style="margin:0 0 24px;color:#4c5159;font-size:15px;line-height:1.7">Thank you for the opportunity to assess your cleaning requirements. Your proposed scope and pricing are summarised below.</p>
    @if(filled($quote->contact_people))
        <div style="margin:0 0 24px;padding:16px;background:#f7f4ec;border-left:4px solid #f5b800;font-size:13px;line-height:1.65">
            <strong style="display:block;margin-bottom:8px;color:#25282d">Project contacts</strong>
            @foreach($quote->contact_people as $contact)
                <p style="margin:0 0 8px"><strong>{{ $contact['name'] }}</strong>@if(filled($contact['role'] ?? null)) &middot; {{ $contact['role'] }}@endif<br>@if(filled($contact['phone'] ?? null)){{ $contact['phone'] }}@endif @if(filled($contact['phone'] ?? null) && filled($contact['email'] ?? null))&middot;@endif @if(filled($contact['email'] ?? null)){{ $contact['email'] }}@endif</p>
            @endforeach
        </div>
    @endif
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;font-size:13px">
        <tr style="color:#fff;background:#25282d"><th align="left" style="padding:11px">Description</th><th align="right" style="padding:11px">Amount</th></tr>
        @foreach($quote->items as $item)
            <tr><td style="padding:13px 11px;border-bottom:1px solid #dedbd2"><strong>{{ $item->description }}</strong>@if($item->notes)<div style="margin-top:7px">{!! app(\App\Support\QuotationHtmlSanitizer::class)->forEmail($item->notes) !!}</div>@endif<span style="color:#73777d">{{ number_format($item->quantity, 2) }} {{ $item->unit }} @ RM {{ number_format($item->unit_price, 2) }}</span></td><td align="right" style="padding:13px 11px;border-bottom:1px solid #dedbd2;vertical-align:top">RM {{ number_format($item->amount, 2) }}</td></tr>
        @endforeach
        <tr><td align="right" style="padding:8px 11px">Subtotal</td><td align="right" style="padding:8px 11px">RM {{ number_format($quote->subtotal, 2) }}</td></tr>
        @if($quote->discount > 0)
            <tr><td align="right" style="padding:8px 11px">Discount</td><td align="right" style="padding:8px 11px">− RM {{ number_format($quote->discount, 2) }}</td></tr>
        @endif
        @if($quote->tax_rate > 0)
            <tr><td align="right" style="padding:8px 11px">Tax ({{ number_format($quote->tax_rate, 2) }}%)</td><td align="right" style="padding:8px 11px">RM {{ number_format($quote->tax_amount, 2) }}</td></tr>
        @endif
        <tr><td align="right" style="padding:18px 11px;font-weight:700">Total</td><td align="right" style="padding:18px 11px;color:#8c6906;font-size:20px;font-weight:700">RM {{ number_format($quote->total, 2) }}</td></tr>
    </table>
    <div style="margin:8px 0 24px;padding:16px;background:#f7f4ec;font-size:13px;line-height:1.6"><strong>Service location</strong><br>{{ $quote->service_address }}<br>{{ $quote->postcode }} {{ $quote->city }}, {{ $quote->state }}</div>
    <div style="margin:0 0 24px;padding:16px;background:#f7f4ec;border-left:4px solid #f5b800;color:#4c5159;font-size:12px;line-height:1.6"><strong style="color:#25282d">Project &amp; payment terms</strong><ol style="margin:10px 0 0;padding-left:20px">@foreach(config('company.quotation_terms') as $term)<li style="margin:0 0 7px">{{ $term }}</li>@endforeach</ol></div>
    <p style="margin:0 0 24px;color:#73777d;font-size:12px;line-height:1.6">Reply to this email or contact us on WhatsApp to accept or discuss this quotation.</p>
    <a href="{{ route('whatsapp.redirect', ['text' => 'Hi CuciNow, I would like to discuss quotation '.$quote->quote_number]) }}" style="display:inline-block;padding:14px 20px;color:#25282d;background:#f5b800;border-radius:9px;font-size:14px;font-weight:700;text-decoration:none">Discuss this quotation</a>
@endsection
