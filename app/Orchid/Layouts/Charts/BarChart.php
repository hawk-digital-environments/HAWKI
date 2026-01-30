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
    // {
    //    return [
    //        [
    //            'label'   => 'Medium',
    //            'value'   => 2,
    //        ],
    //    ];
    // }

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
        '#2ec7c9',
        '#F1C40F',
        '#F75C03',
        '#D90368',
        '#00CC66',
    ];

    /**
     * Configuring bar options for stacked bars.
     *
     * @var array
     */
    protected $barOptions = [
        'spaceRatio' => 0.5,
        'stacked' => 1,  // Enable stacked bars
        'height' => 20,
        'depth' => 2,
    ];
}
