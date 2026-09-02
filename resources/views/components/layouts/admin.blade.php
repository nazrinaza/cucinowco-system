<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>{{ $title ?? 'Admin' }} | CuciNow.co</title><link rel="icon" href="{{ asset('favicon.ico') }}" sizes="64x64"><link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32x32.png') }}"><link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/apple-touch-icon.png') }}">@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="admin-body">
    <div class="admin-shell">
        <aside class="admin-sidebar" data-admin-sidebar>
            <a href="{{ route('admin.dashboard') }}" class="admin-brand"><img src="{{ asset('images/cucinow-logo.png') }}" alt="CuciNow.co"></a>
            <nav>
                @foreach ([
                    ['admin.dashboard','Overview','OV'],['admin.site-visits.*','Site visits','SV'],['admin.quotes.*','Quotes','QU'],['admin.invoices.*','Invoices','IN'],['admin.bookings.*','Bookings','BK'],['admin.customers.*','Customers','CU'],['admin.staff.*','Staff','ST'],['admin.subscribers.*','Subscribers','SU'],['admin.campaigns.*','Campaigns','CA']
                ] as [$route,$label,$icon])
                    <a href="{{ route(str_replace('.*','.index',$route)) }}" @class(['active' => request()->routeIs($route)])><span>{{ $icon }}</span>{{ $label }}</a>
                @endforeach
            </nav>
            <div class="admin-sidebar-foot"><p>Signed in as</p><strong>{{ auth()->user()->name }}</strong><form method="post" action="{{ route('logout') }}">@csrf<button type="submit">Sign out</button></form></div>
        </aside>
        <div class="admin-main">
            <header class="admin-topbar"><button type="button" data-admin-menu aria-label="Toggle menu">Menu</button><div><span>{{ now()->format('l, d M Y') }}</span><a href="{{ route('home') }}" target="_blank">View website &nearr;</a></div></header>
            <main class="admin-content">
                <div class="admin-page-head"><div><p class="admin-kicker">CuciNow operations</p><h1>{{ $heading ?? $title ?? 'Overview' }}</h1></div>{{ $actions ?? '' }}</div>
                @if(session('success'))<div class="admin-alert success">{{ session('success') }}</div>@endif
                @if($errors->any())<div class="admin-alert error"><strong>Please check the form.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
