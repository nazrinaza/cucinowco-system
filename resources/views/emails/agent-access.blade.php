@extends('emails.layout')
@section('title', $heading)
@section('preview', $heading)
@section('content')
    <p style="color:#8c6906;font-size:12px;font-weight:700">CUCINOW AGENT NETWORK</p>
    <h1 style="font-size:28px;line-height:1.2">{{ $heading }}</h1>
    <p style="font-size:15px;line-height:1.7">{{ $messageText }}</p>
    <a href="{{ $actionUrl }}" style="display:inline-block;margin-top:16px;padding:14px 20px;color:#25282d;background:#f5b800;border-radius:9px;font-size:14px;font-weight:700;text-decoration:none">{{ $actionLabel }}</a>
@endsection
