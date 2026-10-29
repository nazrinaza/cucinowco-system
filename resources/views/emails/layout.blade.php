<!doctype html>
@php($isPrint = $printMode ?? false)
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>@yield('title', config('company.name'))</title>
    @if($isPrint)
        <style>
            *{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;box-sizing:border-box}
            .document-print-toolbar{position:sticky;z-index:20;top:0;display:flex;justify-content:center;gap:10px;padding:12px;background:#25282d;font-family:Arial,Helvetica,sans-serif}
            .document-print-toolbar a,.document-print-toolbar button{display:inline-block;padding:10px 15px;border:1px solid #f5b800;border-radius:8px;color:#25282d;background:#f5b800;font-size:13px;font-weight:700;text-decoration:none;cursor:pointer}
            .document-print-toolbar a{color:#fff;background:transparent;border-color:#777b82}
            @page{size:A4;margin:12mm}
            @media print{
                html,body{background:#fff!important}
                .document-print-toolbar{display:none!important}
                .email-page,.email-card{background:#fffdf8!important}
                .email-page,.email-page>tbody,.email-page>tbody>tr,.email-page-cell,.email-card,.email-card>tbody,.email-card-row,.email-card-row>td{display:block!important;width:100%!important}
                .email-page-cell{padding:0!important}
                .email-card{max-width:none!important;border:1px solid #d8d4ca!important;border-radius:18px!important;overflow:visible!important}
                .email-header-row>td{padding:18px 24px!important;background:#f7f4ec!important;border-radius:17px 17px 0 0!important;border-bottom:5px solid #f5b800!important}
                .email-footer-row>td{border-radius:0 0 17px 17px!important}
                .email-content-row>td{padding:24px!important}
                .email-footer-row>td{padding:16px 24px!important}
                .email-card-row,.email-card-row>td,.document-line-table,.document-line-table tr,.document-line-table td{break-inside:auto!important;page-break-inside:auto!important}
                .document-line-table thead{display:table-header-group}
                .document-line-header{break-after:avoid;page-break-after:avoid}
                .document-totals,.document-totals-table{break-inside:avoid!important;page-break-inside:avoid!important}
                .document-line-item p{margin:4px 0 7px!important;line-height:1.42!important}
                .document-line-item ul,.document-line-item ol{margin:5px 0 8px!important}
                .document-line-item li{margin-bottom:3px!important;line-height:1.4!important}
                .document-line-item hr{margin:8px 0!important}
                img{break-inside:avoid;page-break-inside:avoid}
                p,li{orphans:2;widows:2}
            }
        </style>
    @endif
</head>
<body style="margin:0;padding:0;background:#f2efe7;color:#25282d;font-family:Arial,Helvetica,sans-serif">
@if($isPrint)
    <div class="document-print-toolbar"><a href="{{ $printBackUrl }}">Back to document</a><button type="button" onclick="window.print()">Print / Save PDF</button></div>
@endif
<div style="display:none;max-height:0;overflow:hidden;color:transparent">@yield('preview')</div>
<table class="email-page" role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f2efe7">
    <tr><td class="email-page-cell" align="center" style="padding:28px 12px">
        <table class="email-card" role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:660px;background:#fffdf8;border:1px solid #dedbd2;border-radius:18px;overflow:hidden">
            <tr class="email-card-row email-header-row"><td style="padding:24px 30px;background:#25282d;border-bottom:5px solid #f5b800">
                <a href="@yield('header-link', 'https://cucinow.co')" target="_blank" rel="noopener noreferrer" style="display:inline-block;text-decoration:none">
                    <img src="{{ asset('images/cucinow-logo.png') }}" width="190" alt="CuciNow.co by Thursina" style="display:block;max-width:190px;height:auto;border:0">
                </a>
            </td></tr>
            <tr class="email-card-row email-content-row"><td style="padding:36px 30px">
                @yield('content')
            </td></tr>
            <tr class="email-card-row email-footer-row"><td style="padding:24px 30px;color:#73777d;background:#f7f4ec;border-top:1px solid #dedbd2;font-size:12px;line-height:1.6">
                <strong style="color:#25282d">{{ config('company.name') }} by {{ config('company.legal_name') }}</strong><br>
                {{ config('company.address') }}<br>
                <a href="mailto:{{ config('company.email') }}" style="color:#8c6906">{{ config('company.email') }}</a>
                &nbsp;&middot;&nbsp;
                <a href="{{ route('whatsapp.redirect') }}" style="color:#8c6906">WhatsApp +60 11-1242 8593</a>
                @hasSection('footer')
                    <div style="margin-top:16px;padding-top:14px;border-top:1px solid #dedbd2">@yield('footer')</div>
                @endif
            </td></tr>
        </table>
    </td></tr>
</table>
@if($isPrint)
    <script>window.addEventListener('load',function(){window.setTimeout(function(){window.print()},150)})</script>
@endif
</body>
</html>
