<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() == 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0" />
    <title> {{ env('APP_NAME') }} - إختبار سرعة الكتابة</title>

    <!-- SEO Meta Tags -->
    <meta name="description"
        content="Enhance your Quran memorization and Arabic typing speed with QuranTyping. Progress through the Quran, test your accuracy, and track your performance in a beautiful, minimalist environment.">
    <meta name="keywords"
        content="Quran Typing, Arabic Typing Test, Memorize Quran, Quran Hifz Tool, Learn Arabic Typing, Speed Typing Arabic, Islamic Typing Website">
    <meta name="author" content="QuranTyping">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="QuranTyping - Quran Memorization & Arabic Typing Tool">
    <meta property="og:description"
        content="Enhance your Quran memorization and Arabic typing speed with QuranTyping. Test your accuracy and track your performance.">
    <meta property="og:image" content="{{ asset('images/og-image.jpg') }}">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="{{ url()->current() }}">
    <meta property="twitter:title" content="QuranTyping - Quran Memorization & Arabic Typing Tool">
    <meta property="twitter:description"
        content="Enhance your Quran memorization and Arabic typing speed with QuranTyping. Test your accuracy and track your performance.">
    <meta property="twitter:image" content="{{ asset('images/og-image.jpg') }}">

    {{-- Import a beautiful Arabic font --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Noto+Naskh+Arabic:wght@400..700&family=Cinzel:wght@400..900&family=Playfair+Display:ital,wght@0,400..900;1,400..900&display=swap"
        rel="stylesheet">

    @vite('resources/js/app.js')
    @inertiaHead
</head>

<body class="bg-gray-900 text-gray-200 antialiased">
    @inertia
</body>
{{-- Ensure the @routes directive is here --}}
@routes

</html>