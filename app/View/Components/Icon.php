<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Icon extends Component
{
    public string $name;
    public ?string $class;

    /**
     * Create a new component instance.
     */
    public function __construct(string $name, ?string $class = null, ?int $width = 24, ?int $height = 24, ?string $fill = 'currentColor')
    {
        $this->name = $name;
        $this->class = $class;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render()
    {
        return view('components.icon');
    }
}
