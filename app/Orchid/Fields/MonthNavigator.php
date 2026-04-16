<?php

namespace App\Orchid\Fields;

use Orchid\Screen\Field;

class MonthNavigator extends Field
{
    /**
     * @var string
     */
    protected $view = 'orchid.fields.month-navigator';

    /**
     * Attributes available for a particular tag.
     *
     * @var array
     */
    protected $inlineAttributes = [
        'name',
        'value',
    ];

    /**
     * Set the current month value.
     *
     * @param  string  $month  Format: Y-m (e.g., "2025-12")
     */
    public function currentMonth(string $month): self
    {
        $this->set('currentMonth', $month);

        return $this;
    }

    /**
     * Set the route name for navigation.
     */
    public function route(string $route): self
    {
        $this->set('routeName', $route);

        return $this;
    }
}
