<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $templateData['subject'] ?? 'E-Mail von ' . config('app.name') }}</title>
</head>
<body>
    {!! $templateData['body'] ?? '<p>E-Mail Inhalt konnte nicht geladen werden.</p>' !!}
</body>
</html>
