<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0" />
    <title> {{ env('APP_NAME') }} - إختبار سرعة الكتابة</title>

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