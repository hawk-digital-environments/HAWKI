<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="{{ asset('css_v2.0.1_f1/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css_v2.0.1_f1/home-style.css') }}">

    <title>Document</title>
</head>
<body>
    <div class="scroll-container">
        <div class="scroll-panel">
            <div class="dataprotection">
                {!! $translation["_DataProtection"] !!}
            </div>
        </div>
    </div>
</body>
</html>

<style>
    .dataprotection{
        margin: 0 auto;
        max-width: 65rem
    }
</style>
