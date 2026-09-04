@extends('emails.layout')
@section('title', 'New site visit request')
@section('preview', 'A new customer has requested a complimentary site visit.')
@section('content')
    <p style="margin:0 0 10px;color:#8c6906;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.08em">New website lead</p>
    <h1 style="margin:0 0 18px;font-size:30px">{{ $siteVisit->reference_number }}</h1>
    <p style="margin:0 0 22px;color:#4c5159;font-size:15px;line-height:1.7"><strong>{{ $siteVisit->customer->name }}</strong>{{ $siteVisit->customer->company_name ? ' from '.$siteVisit->customer->company_name : '' }} requested a free site visit.</p>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="8" style="margin-bottom:24px;border-collapse:collapse;font-size:13px">
        <tr><td style="color:#73777d;border-bottom:1px solid #dedbd2">Service</td><td style="border-bottom:1px solid #dedbd2"><strong>{{ $siteVisit->service?->name }}</strong></td></tr>
        <tr><td style="color:#73777d;border-bottom:1px solid #dedbd2">Preferred</td><td style="border-bottom:1px solid #dedbd2">{{ $siteVisit->preferred_date?->format('d M Y') }} &middot; {{ str($siteVisit->preferred_time_slot)->replace('_', ' ')->title() }}</td></tr>
        <tr><td style="color:#73777d;border-bottom:1px solid #dedbd2">Contact</td><td style="border-bottom:1px solid #dedbd2">{{ $siteVisit->customer->phone }}<br>{{ $siteVisit->customer->email }}</td></tr>
        <tr><td style="color:#73777d">Location</td><td>{{ $siteVisit->site_address }}, {{ $siteVisit->postcode }}</td></tr>
    </table>
    <a href="{{ route('admin.site-visits.show', $siteVisit) }}" style="display:inline-block;padding:14px 20px;color:#25282d;background:#f5b800;border-radius:9px;font-size:14px;font-weight:700;text-decoration:none">Review request</a>
@endsection
