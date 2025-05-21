<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Charts;

use Orchid\Screen\Layouts\Chart;

class BarChart extends Chart
{
    /**
     * Available options:
     * 'bar', 'line',
     * 'pie', 'percentage'.
     *
     * @var string
     */
    protected $type = self::TYPE_BAR;

    /**
     * Height of the chart.
     *
     * @var int
     */
    protected $height = 300;

    /**
     * To highlight certain values on the Y axis, markers can be set.
     * They will show as dashed lines on the graph.
     */
    // protected function markers(): ?array
    //{
    //    return [
    //        [
    //            'label'   => 'Medium',
    //            'value'   => 2,
    //        ],
    //    ];
    //}

    /**
     * Determines whether to display the export button.
     *
     * @var bool
     */
     protected $export = true;

    /**
     * Colors used.
     *
     * @var array
     */
    protected $colors = [
        '#2274A5',
        '#F75C03',
        '#F1C40F',
        '#D90368',
        '#00CC66',
    ]; 

    
}
