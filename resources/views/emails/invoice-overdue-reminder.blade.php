@extends('emails.layout')
@section('title', 'Payment reminder '.$invoice->invoice_number)
@section('preview', 'A friendly reminder that your CuciNow invoice is overdue.')
@section('content')
    <p style="margin:0 0 10px;color:#8c6906;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.08em">Friendly payment reminder</p>
    <h1 style="margin:0 0 18px;font-size:30px">Invoice {{ $invoice->invoice_number }}</h1>
    <p style="margin:0 0 24px;color:#4c5159;font-size:15px;line-height:1.7">Our records show a remaining balance that was due on {{ $invoice->due_at?->format('d M Y') }}. If payment has already been made, please disregard this reminder or send us the transaction reference.</p>
    <div style="margin-bottom:24px;padding:20px;background:#f5b800;border-radius:10px"><span style="font-size:12px;font-weight:700;text-transform:uppercase">Balance due</span><br><strong style="font-size:28px">RM {{ number_format($invoice->balance, 2) }}</strong></div>
    <a href="https://wa.me/{{ config('company.whatsapp') }}?text={{ urlencode('Hi CuciNow, I am contacting you about payment for invoice '.$invoice->invoice_number) }}" style="display:inline-block;padding:14px 20px;color:#25282d;background:#f5b800;border-radius:9px;font-size:14px;font-weight:700;text-decoration:none">Contact accounts</a>
@endsection
