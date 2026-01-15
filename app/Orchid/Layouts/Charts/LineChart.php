<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Charts;

use Orchid\Screen\Layouts\Chart;

class LineChart extends Chart
{
    /**
     * Available options:
     * 'bar', 'line',
     * 'pie', 'percentage'.
     *
     * @var string
     */
    protected $type = self::TYPE_LINE;

    /**
     * Height of the chart.
     *
     * @var int
     */
    protected $height = 300;

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
        '#F75C03',
        '#F1C40F',
        '#D90368',
        '#00CC66',
    ];

    /**
     * Configuring line options.
     *
     * @var array
     */
    protected $lineOptions = [
        'regionFill' => 1,
        'hideDots' => 0,
        'hideLine' => 0,
        'heatline' => 0,
        'dotSize' => 3,
        // Spline interpolation is disabled (set to 0) because adjacent data points 
        // transitioning from n to 0 produce 'NaN' values in the SVG path by Frappe Charts.
        'spline' => 0,
    ];
}
