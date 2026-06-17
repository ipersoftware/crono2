<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ ($meta['title'] ?? null) ?: config('app.name', 'Crono') }}</title>

        @isset($meta)
        <meta name="description"        content="{{ $meta['description'] }}">
        <meta property="og:type"        content="{{ $meta['type'] ?? 'website' }}">
        <meta property="og:title"       content="{{ $meta['title'] }}">
        <meta property="og:description" content="{{ $meta['description'] }}">
        @if(!empty($meta['image']))
        <meta property="og:image"       content="{{ $meta['image'] }}">
        <meta name="twitter:image"      content="{{ $meta['image'] }}">
        @endif
        <meta property="og:url"         content="{{ $meta['url'] }}">
        <meta property="og:site_name"   content="{{ $meta['site_name'] ?? config('app.name') }}">
        <meta name="twitter:card"       content="summary_large_image">
        <meta name="twitter:title"      content="{{ $meta['title'] }}">
        <meta name="twitter:description"content="{{ $meta['description'] }}">
        <link rel="canonical"           href="{{ $meta['url'] }}">
        @if(!empty($meta['json_ld']))
        <script type="application/ld+json">{!! $meta['json_ld'] !!}</script>
        @endif
        @endisset

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div id="app"></div>
    </body>
</html>
