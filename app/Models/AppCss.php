<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppCss extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'app_css';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'content',
        'active',
    ];

    /**
     * Get active CSS by name
     *
     * @param string $name
     * @return string|null
     */
    public static function getByName(string $name): ?string
    {
        $css = self::where('name', $name)
            ->where('active', true)
            ->first();
            
        return $css ? $css->content : null;
    }

    /**
     * Get all active CSS files
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getAllActive()
    {
        return self::where('active', true)->get();
    }
}
