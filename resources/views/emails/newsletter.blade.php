@extends('emails.layout')
@section('title', $campaign->subject)
@section('preview', $campaign->preview_text ?: $campaign->subject)
@section('content')
    @if($campaign->preview_text)<p style="margin:0 0 10px;color:#8c6906;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.08em">{{ $campaign->preview_text }}</p>@endif
    <h1 style="margin:0 0 22px;font-size:30px;line-height:1.15">{{ $campaign->subject }}</h1>
    <div style="color:#4c5159;font-size:15px;line-height:1.75">{!! nl2br(e($campaign->content)) !!}</div>
    <div style="margin-top:28px"><a href="{{ url('/#site-visit') }}" style="display:inline-block;padding:14px 20px;color:#25282d;background:#f5b800;border-radius:9px;font-size:14px;font-weight:700;text-decoration:none">Book a free site visit</a></div>
@endsection
@section('footer')
    You received this because {{ $subscriber->email }} subscribed to CuciNow updates.
    <a href="{{ $unsubscribeUrl }}" style="color:#8c6906">Unsubscribe</a>.
@endsection
