@extends('emails.layout')
@section('title', 'Invoice '.$invoice->invoice_number)
@section('preview', 'Your CuciNow invoice is ready.')
@section('content')
    <p style="margin:0 0 10px;color:#8c6906;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.08em">Invoice</p>
    <h1 style="margin:0 0 8px;font-size:30px">{{ $invoice->invoice_number }}</h1>
    <p style="margin:0 0 24px;color:#73777d;font-size:13px">Issued {{ $invoice->issued_at?->format('d M Y') }} &middot; Due {{ $invoice->due_at?->format('d M Y') }}</p>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;font-size:13px">
        <tr style="color:#fff;background:#25282d"><th align="left" style="padding:11px">Description</th><th align="right" style="padding:11px">Amount</th></tr>
        @foreach($invoice->items as $item)
            <tr><td style="padding:13px 11px;border-bottom:1px solid #dedbd2"><strong>{{ $item->description }}</strong><br><span style="color:#73777d">{{ number_format($item->quantity, 2) }} {{ $item->unit }} @ RM {{ number_format($item->unit_price, 2) }}</span></td><td align="right" style="padding:13px 11px;border-bottom:1px solid #dedbd2">RM {{ number_format($item->amount, 2) }}</td></tr>
        @endforeach
    </table>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="6" style="margin:15px 0 24px;font-size:13px">
        <tr><td align="right">Subtotal</td><td align="right" width="145">RM {{ number_format($invoice->subtotal, 2) }}</td></tr>
        @if($invoice->discount > 0)<tr><td align="right">Discount</td><td align="right">- RM {{ number_format($invoice->discount, 2) }}</td></tr>@endif
        @if($invoice->tax_rate > 0)<tr><td align="right">SST ({{ number_format($invoice->tax_rate, 0) }}%)</td><td align="right">RM {{ number_format($invoice->tax_amount, 2) }}</td></tr>@endif
        <tr><td align="right" style="font-weight:700">Total</td><td align="right" style="font-weight:700">RM {{ number_format($invoice->total, 2) }}</td></tr>
        <tr style="background:#f5b800"><td align="right" style="font-weight:700">Balance due</td><td align="right" style="font-size:18px;font-weight:700">RM {{ number_format($invoice->balance, 2) }}</td></tr>
    </table>
    <div style="margin-bottom:24px;padding:16px;background:#f7f4ec;font-size:13px;line-height:1.6"><strong>Payment terms</strong><br>{{ $invoice->payment_terms }}@if($invoice->notes)<br><br>{{ $invoice->notes }}@endif</div>
    <a href="https://wa.me/{{ config('company.whatsapp') }}?text={{ urlencode('Hi CuciNow, I am contacting you about invoice '.$invoice->invoice_number) }}" style="display:inline-block;padding:14px 20px;color:#25282d;background:#f5b800;border-radius:9px;font-size:14px;font-weight:700;text-decoration:none">Contact accounts</a>
@endsection
