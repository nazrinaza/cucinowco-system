@extends('emails.layout')
@section('title', 'Payment receipt '.$payment->payment_number)
@section('preview', 'We have recorded your payment to CuciNow.')
@section('content')
    <p style="margin:0 0 10px;color:#8c6906;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.08em">Payment received</p>
    <h1 style="margin:0 0 18px;font-size:30px">Thank you.</h1>
    <p style="margin:0 0 24px;color:#4c5159;font-size:15px;line-height:1.7">We have recorded your payment against invoice <strong>{{ $payment->invoice->invoice_number }}</strong>.</p>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="9" style="margin-bottom:24px;border-collapse:collapse;font-size:13px">
        <tr><td style="color:#73777d;border-bottom:1px solid #dedbd2">Receipt</td><td align="right" style="border-bottom:1px solid #dedbd2"><strong>{{ $payment->payment_number }}</strong></td></tr>
        <tr><td style="color:#73777d;border-bottom:1px solid #dedbd2">Paid on</td><td align="right" style="border-bottom:1px solid #dedbd2">{{ $payment->paid_at->format('d M Y, g:i A') }}</td></tr>
        <tr><td style="color:#73777d;border-bottom:1px solid #dedbd2">Method</td><td align="right" style="border-bottom:1px solid #dedbd2">{{ str($payment->method)->replace('_', ' ')->title() }}</td></tr>
        <tr style="background:#f5b800"><td style="font-weight:700">Amount</td><td align="right" style="font-size:18px;font-weight:700">RM {{ number_format($payment->amount, 2) }}</td></tr>
    </table>
    <p style="margin:0;color:#73777d;font-size:12px;line-height:1.6">Remaining invoice balance: <strong>RM {{ number_format($payment->invoice->balance, 2) }}</strong>.</p>
@endsection
