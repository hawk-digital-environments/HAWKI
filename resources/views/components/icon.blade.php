@php
    $iconPath = "icons.{$name}";
@endphp

@includeIf($iconPath, [
    'class' => $class,
])
