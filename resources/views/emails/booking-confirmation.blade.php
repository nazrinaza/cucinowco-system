@extends('emails.layout')
@section('title', 'Booking '.$booking->booking_number)
@section('preview', 'Your CuciNow cleaning appointment is confirmed.')
@section('content')
    <p style="margin:0 0 10px;color:#8c6906;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.08em">Booking confirmed</p>
    <h1 style="margin:0 0 18px;font-size:30px">{{ $booking->booking_number }}</h1>
    <p style="margin:0 0 24px;color:#4c5159;font-size:15px;line-height:1.7">Hello {{ $booking->customer->name }}, your CuciNow cleaning appointment has been scheduled.</p>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="9" style="margin-bottom:24px;border-collapse:collapse;font-size:13px">
        <tr><td style="color:#73777d;border-bottom:1px solid #dedbd2">Service</td><td style="border-bottom:1px solid #dedbd2"><strong>{{ $booking->service?->name ?? $booking->quote?->items?->first()?->description ?? 'Cleaning service' }}</strong></td></tr>
        <tr><td style="color:#73777d;border-bottom:1px solid #dedbd2">Date</td><td style="border-bottom:1px solid #dedbd2">{{ $booking->scheduled_start?->format('l, d M Y') }}</td></tr>
        <tr><td style="color:#73777d;border-bottom:1px solid #dedbd2">Time</td><td style="border-bottom:1px solid #dedbd2">{{ $booking->scheduled_start?->format('g:i A') }}@if($booking->scheduled_end) – {{ $booking->scheduled_end->format('g:i A') }}@endif</td></tr>
        <tr><td style="color:#73777d">Location</td><td>{{ $booking->service_address }}</td></tr>
    </table>
    <p style="margin:0 0 24px;color:#73777d;font-size:12px;line-height:1.6">Please ensure the team can access the service area at the confirmed time. Contact us if the schedule or access details change.</p>
    <a href="https://wa.me/{{ config('company.whatsapp') }}?text={{ urlencode('Hi CuciNow, I am contacting you about booking '.$booking->booking_number) }}" style="display:inline-block;padding:14px 20px;color:#25282d;background:#f5b800;border-radius:9px;font-size:14px;font-weight:700;text-decoration:none">Manage via WhatsApp</a>
@endsection
