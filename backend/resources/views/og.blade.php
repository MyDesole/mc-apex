<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    <meta name="theme-color" content="#7c3aed">

    <meta property="og:type" content="profile">
    <meta property="og:site_name" content="APEX TIERS">
    <meta property="og:locale" content="ru_RU">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $url }}">
    <meta property="og:image" content="{{ $image }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ $image }}">

    <link rel="canonical" href="{{ $url }}">
</head>
<body>
    <a href="{{ $url }}">{{ $title }}</a>
</body>
</html>
