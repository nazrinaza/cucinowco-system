@extends('emails.layout')
@section('title', 'Free site visit request received')
@section('preview', 'Your CuciNow site visit request has been received.')
@section('content')
    <p style="margin:0 0 10px;color:#8c6906;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.08em">Request received</p>
    <h1 style="margin:0 0 18px;font-size:31px;line-height:1.12">Thank you, {{ $siteVisit->customer->name }}.</h1>
    <p style="margin:0 0 24px;color:#4c5159;font-size:15px;line-height:1.7">We have received your complimentary site visit request. Our team will review the location and contact you to confirm the appointment.</p>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 26px;background:#f7f4ec;border-left:5px solid #f5b800">
        <tr><td style="padding:20px">
            <strong style="font-size:18px">{{ $siteVisit->reference_number }}</strong><br>
            <span style="color:#73777d;font-size:13px;line-height:1.7">{{ $siteVisit->service?->name }}<br>{{ $siteVisit->preferred_date?->format('d M Y') }} &middot; {{ str($siteVisit->preferred_time_slot)->replace('_', ' ')->title() }}<br>{{ $siteVisit->site_address }}, {{ $siteVisit->postcode }}</span>
        </td></tr>
    </table>
    <a href="https://wa.me/{{ config('company.whatsapp') }}?text={{ urlencode('Hi CuciNow, my free site visit reference is '.$siteVisit->reference_number) }}" style="display:inline-block;padding:14px 20px;color:#25282d;background:#f5b800;border-radius:9px;font-size:14px;font-weight:700;text-decoration:none">Continue on WhatsApp</a>
@endsection
