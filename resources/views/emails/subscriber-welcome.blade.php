@extends('emails.layout')
@section('title', 'Welcome to CuciNow updates')
@section('preview', 'Useful cleaning notes and service updates, without the clutter.')
@section('content')
    <p style="margin:0 0 10px;color:#8c6906;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.08em">Subscription confirmed</p>
    <h1 style="margin:0 0 18px;font-size:30px">Useful cleaning notes. No clutter.</h1>
    <p style="margin:0 0 24px;color:#4c5159;font-size:15px;line-height:1.7">Thank you for subscribing. We will occasionally send practical cleaning guidance, service reminders and CuciNow updates.</p>
    <a href="{{ url('/#site-visit') }}" style="display:inline-block;padding:14px 20px;color:#25282d;background:#f5b800;border-radius:9px;font-size:14px;font-weight:700;text-decoration:none">Explore CuciNow</a>
@endsection
@section('footer')
    You subscribed with {{ $subscriber->email }}.
    <a href="{{ $unsubscribeUrl }}" style="color:#8c6906">Unsubscribe</a>.
@endsection
