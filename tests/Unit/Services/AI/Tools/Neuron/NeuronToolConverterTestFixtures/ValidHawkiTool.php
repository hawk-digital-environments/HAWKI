<?php
declare(strict_types=1);

namespace Tests\Unit\Services\AI\Tools\Neuron\NeuronToolConverterTestFixtures;

use App\Services\Ai\Tools\AbstractTool;

/**
 * Minimal valid HAWKI function tool used to verify that NeuronToolConverter
 * can resolve and return a properly constructed tool instance.
 */
class ValidHawkiTool extends AbstractTool
{
    protected function name(): string
    {
        return 'valid_test_tool';
    }

    protected function description(): string
    {
        return 'A fixture tool for NeuronToolConverterTest.';
    }
}
