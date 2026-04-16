<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} - UPDATING</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">

</head>
<body>
    {{-- 
    ⚠️ DEPRECATED: This file is no longer actively used.
    The maintenance page functionality has been moved to: resources/views/errors/503.blade.php
    
    Use standard Laravel maintenance mode instead:
    php artisan down
    php artisan up
    --}}
    
    <div class="container">
        <div class="content">
            <img id="HAWK_logo" src="{{ asset('img/logo.svg')}}" alt="">
            <h1>Welcome to {{ config('app.name') }}</h1>
            <img src="https://i.pinimg.com/originals/ed/77/47/ed7747ca797333eb6447917b803af306.gif" alt="">
            <h2>We are updating {{ config('app.name') }} right now!<br>Please try again later :)</h2>
        </div>
    </div>

</body>
</html>

<style>
    @import url("https://fonts.googleapis.com/css2?family=Fira+Sans:wght@300;400;500;600;700&display=swap");

    body {
    overflow: hidden;
    -ms-overflow-style: none; 
    font-family: "Fira Sans", sans-serif;
    color: var(--text-color);
    background-color: var(--background-color);
    background-color: black;
    }
    .container{
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        color: white;
    }
    .content {
        margin: auto;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 100%;
    }
    h2{
        text-align:center;
    }
    #HAWK_logo {
    max-width: 10rem;
    height: auto;
    }

</style>