@extends('emails.layout')
@section('title', 'Quotation '.$quote->quote_number)
@section('preview', 'Your CuciNow quotation is ready for review.')
@section('content')
    <p style="margin:0 0 10px;color:#8c6906;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.08em">Quotation</p>
    <h1 style="margin:0 0 8px;font-size:30px">{{ $quote->quote_number }}</h1>
    <p style="margin:0 0 24px;color:#73777d;font-size:13px">Prepared for {{ $quote->customer->name }} &middot; Valid until {{ $quote->valid_until?->format('d M Y') }}</p>
    <p style="margin:0 0 24px;color:#4c5159;font-size:15px;line-height:1.7">Thank you for the opportunity to assess your cleaning requirements. Your proposed scope and pricing are summarised below.</p>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;font-size:13px">
        <tr style="color:#fff;background:#25282d"><th align="left" style="padding:11px">Description</th><th align="right" style="padding:11px">Amount</th></tr>
        @foreach($quote->items as $item)
            <tr><td style="padding:13px 11px;border-bottom:1px solid #dedbd2"><strong>{{ $item->description }}</strong><br><span style="color:#73777d">{{ number_format($item->quantity, 2) }} {{ $item->unit }} @ RM {{ number_format($item->unit_price, 2) }}</span></td><td align="right" style="padding:13px 11px;border-bottom:1px solid #dedbd2">RM {{ number_format($item->amount, 2) }}</td></tr>
        @endforeach
        <tr><td align="right" style="padding:18px 11px;font-weight:700">Total</td><td align="right" style="padding:18px 11px;color:#8c6906;font-size:20px;font-weight:700">RM {{ number_format($quote->total, 2) }}</td></tr>
    </table>
    <div style="margin:8px 0 24px;padding:16px;background:#f7f4ec;font-size:13px;line-height:1.6"><strong>Service location</strong><br>{{ $quote->service_address }}<br>{{ $quote->postcode }} {{ $quote->city }}, {{ $quote->state }}</div>
    <p style="margin:0 0 24px;color:#73777d;font-size:12px;line-height:1.6">Final scope, timing, availability and price are subject to confirmation by CuciNow. Reply to this email or contact us on WhatsApp to accept or discuss this quotation.</p>
    <a href="https://wa.me/{{ config('company.whatsapp') }}?text={{ urlencode('Hi CuciNow, I would like to discuss quotation '.$quote->quote_number) }}" style="display:inline-block;padding:14px 20px;color:#25282d;background:#f5b800;border-radius:9px;font-size:14px;font-weight:700;text-decoration:none">Discuss this quotation</a>
@endsection
