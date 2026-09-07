<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() == 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0" />
    @php($meta = $meta ?? [])
    <title>{{ $meta['title'] ?? config('app.name') . ' - إختبار سرعة الكتابة' }}</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">

    <!-- Google tag (gtag.js) -->
    @production
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-WXG9JC9FYZ"></script>
    <script>
        window.dataLayer = window.dataLayer || [];

        function gtag() {
            dataLayer.push(arguments);
        }
        gtag('js', new Date());

        gtag('config', 'G-WXG9JC9FYZ');
    </script>
    @endproduction

    <!-- SEO Meta Tags -->
    @php($ogTitle = $meta['title'] ?? 'QuranTyping - Quran Memorization & Arabic Typing Tool')
    @php($ogDescription = $meta['description'] ?? 'Enhance your Quran memorization and Arabic typing speed with QuranTyping. Test your accuracy and track your performance.')
    @php($ogUrl = $meta['url'] ?? url()->current())
    @php($ogImage = $meta['image'] ?? asset('images/og-image.png'))
    @php($ogImageAlt = $meta['image_alt'] ?? 'QuranTyping — type the Qur\'an, letter by letter')
    <meta name="description" content="{{ $ogDescription }}">
    <meta name="keywords"
        content="Quran Typing, Arabic Typing Test, Memorize Quran, Quran Hifz Tool, Learn Arabic Typing, Speed Typing Arabic, Islamic Typing Website">
    <meta name="author" content="QuranTyping">
    <link rel="canonical" href="{{ $ogUrl }}">

    <!-- Open Graph / Facebook -->
    <meta property="og:site_name" content="QuranTyping">
    <meta property="og:locale" content="{{ ['en' => 'en_US', 'fr' => 'fr_FR', 'ar' => 'ar_AR'][app()->getLocale()] ?? 'en_US' }}">
    <meta property="og:type" content="{{ $meta['type'] ?? 'website' }}">
    <meta property="og:url" content="{{ $ogUrl }}">
    <meta property="og:title" content="{{ $ogTitle }}">
    <meta property="og:description" content="{{ $ogDescription }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:image:secure_url" content="{{ $ogImage }}">
    <meta property="og:image:type" content="image/png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="{{ $ogImageAlt }}">

    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="{{ $ogUrl }}">
    <meta name="twitter:title" content="{{ $ogTitle }}">
    <meta name="twitter:description" content="{{ $ogDescription }}">
    <meta name="twitter:image" content="{{ $ogImage }}">
    <meta name="twitter:image:alt" content="{{ $ogImageAlt }}">

    {{-- Structured data (JSON-LD built by the app view composer) --}}
    <script type="application/ld+json">{!! $structuredDataJson !!}</script>

    {{-- Naskh for scripture, IBM Plex for the interface --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600;700&family=Noto+Naskh+Arabic:wght@400..700&display=swap"
        rel="stylesheet">

    @vite('resources/js/app.js')
    @inertiaHead
</head>

<body class="antialiased">
    @inertia
</body>
{{-- Ensure the @routes directive is here --}}
@routes

</html>
