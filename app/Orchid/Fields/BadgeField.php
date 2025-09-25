<?php

namespace App\Orchid\Fields;

use Orchid\Screen\Field;

class BadgeField extends Field
{
    /**
     * @var string
     */
    protected $view = 'orchid.fields.badge';

    /**
     * Attributes available for a particular tag.
     *
     * @var array
     */
    protected $inlineAttributes = [
        'form',
        'formaction',
        'formenctype',
        'formmethod',
        'formnovalidate',
        'formtarget',
        'name',
        'type',
        'value',
    ];

    /**
     * Set the badge color class.
     *
     * @param string $class
     * @return self
     */
    public function badgeClass(string $class): self
    {
        $this->set('badgeClass', $class);
        
        return $this;
    }

    /**
     * Set the badge text content.
     *
     * @param string $text
     * @return self
     */
    public function text(string $text): self
    {
        $this->set('text', $text);
        
        return $this;
    }
}