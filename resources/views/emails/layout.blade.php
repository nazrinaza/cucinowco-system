<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>@yield('title', config('company.name'))</title>
</head>
<body style="margin:0;padding:0;background:#f2efe7;color:#25282d;font-family:Arial,Helvetica,sans-serif">
<div style="display:none;max-height:0;overflow:hidden;color:transparent">@yield('preview')</div>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f2efe7">
    <tr><td align="center" style="padding:28px 12px">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:660px;background:#fffdf8;border:1px solid #dedbd2;border-radius:18px;overflow:hidden">
            <tr><td style="padding:24px 30px;background:#25282d;border-bottom:5px solid #f5b800">
                <img src="{{ asset('images/cucinow-logo.png') }}" width="190" alt="CuciNow.co by Thursina" style="display:block;max-width:190px;height:auto">
            </td></tr>
            <tr><td style="padding:36px 30px">
                @yield('content')
            </td></tr>
            <tr><td style="padding:24px 30px;color:#73777d;background:#f7f4ec;border-top:1px solid #dedbd2;font-size:12px;line-height:1.6">
                <strong style="color:#25282d">{{ config('company.name') }} by {{ config('company.legal_name') }}</strong><br>
                {{ config('company.address') }}<br>
                <a href="mailto:{{ config('company.email') }}" style="color:#8c6906">{{ config('company.email') }}</a>
                &nbsp;&middot;&nbsp;
                <a href="https://wa.me/{{ config('company.whatsapp') }}" style="color:#8c6906">WhatsApp +60 11-1242 8593</a>
                @hasSection('footer')
                    <div style="margin-top:16px;padding-top:14px;border-top:1px solid #dedbd2">@yield('footer')</div>
                @endif
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
