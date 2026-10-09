@props(['area' => 'public'])

@php($googleTagId = config('services.google.tag_id'))

@if(filled($googleTagId))
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ rawurlencode($googleTagId) }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('set', {'site_area': @js($area)});
        gtag('config', @js($googleTagId));
    </script>
@endif
