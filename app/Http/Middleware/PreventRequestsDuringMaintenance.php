<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance as Middleware;

class PreventRequestsDuringMaintenance extends Middleware
{
    /**
     * Die URLs, die während des Wartungsmodus erreichbar bleiben sollen.
     *
     * @var array<int, string>
     */
    protected $except = [
        '/admin',
        '/admin/*', // Für alle Admin-Unterpfade
    ];
}