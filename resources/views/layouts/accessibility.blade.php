<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="{{ asset('css_v2.1.0/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css_v2.1.0/home-style.css') }}">

    <title>{{ $translation["Accessibility"] }}</title>
</head>
<body>
    <div class="scroll-container">
        <div class="scroll-panel">
            <div class="accessibility">
                {!! $translation["_Accessibility"] !!}
            </div>
        </div>
    </div>
</body>
</html>

<style>
    .accessibility{
        margin: 0 auto;
        max-width: 65rem
    }
</style>
