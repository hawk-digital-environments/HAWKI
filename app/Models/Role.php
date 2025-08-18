<?php

namespace App\Models;

use Orchid\Filters\Types\Like;
use Orchid\Filters\Types\Where;
use Orchid\Platform\Models\Role as OrchidRole;

class Role extends OrchidRole
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'name', 
        'slug', 
        'permissions',
        'selfassign', // Füge selfassign zu den fillable Feldern hinzu
    ];

    /**
     * The attributes for which you can use filters in url.
     *
     * @var array
     */
    protected $allowedFilters = [
        'id'         => Where::class,
        'name'       => Like::class,
        'slug'       => Like::class,
        'selfassign' => Where::class,
    ];

    /**
     * The attributes for which can use sort in url.
     *
     * @var array
     */
    protected $allowedSorts = [
        'id',
        'name',
        'slug',
        'selfassign',
        'created_at',
        'updated_at',
    ];
}
