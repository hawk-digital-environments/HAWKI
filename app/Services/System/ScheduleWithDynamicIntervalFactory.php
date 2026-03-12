<?php
declare(strict_types=1);

namespace App\Services\System;

use App\Providers\AppServiceProvider;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\ManagesFrequencies;
use Illuminate\Support\Facades\Schedule;
use Psr\Log\LoggerInterface;

readonly class ScheduleWithDynamicIntervalFactory
{
    /**
     * A list of methods from the ManagesFrequencies trait that are not allowed to be used as intervals for scheduling jobs,
     * simply because they do not make sense in the context of scheduling (e.g., "timezone" is used to set the timezone for the schedule, not as an interval).
     */
    private const DENIED_INTERVALS = ['timezone'];

    /**
     * If given as interval, the job will never be executed.
     */
    public const NEVER_INTERVAL = 'never';

    public function __construct(
        private LoggerInterface $logger
    )
    {
    }

    /**
     * Creates a scheduled job with a dynamic interval and arguments.
     *
     * This is used by the {@see AppServiceProvider::bootSchedulerMacros()} method to create scheduled jobs based on dynamic intervals and arguments,
     * which can be defined in the database or configuration.
     *
     * @param string $command The command to be scheduled.
     * @param array|null $parameters Optional parameters for the command.
     * @param mixed $interval The scheduling interval, which can be a string representing a scheduling method or the special "never" value.
     * @param mixed|null $intervalArgs Optional arguments for the scheduling method, which can be a JSON string, a single numeric value, or a simple string.
     * @return Event|null Returns the scheduled Event if successful, or null if there was an error in scheduling due to invalid interval or arguments.
     */
    public function makeJob(
        string     $command,
        array|null $parameters,
        mixed      $interval,
        mixed      $intervalArgs = null
    ): Event|null
    {
        if ($interval === self::NEVER_INTERVAL) {
            return null;
        }

        $intervalArgsArray = $this->parseIntervalArgs($intervalArgs);
        $validationPassed = $this->validateIntervalAndArgs($interval, $intervalArgsArray, $command);
        if (!$validationPassed) {
            return null;
        }

        try {
            return Schedule::command($command, $parameters ?? [])
                ->$interval(...$intervalArgsArray);
        } catch (\TypeError|\InvalidArgumentException $e) {
            $this->logger->error(
                sprintf(
                    'Failed to schedule command "%s" with interval "%s" and arguments %s. Error: %s',
                    $command,
                    $interval,
                    json_encode($intervalArgsArray),
                    $e->getMessage()
                )
            );
        }

        return null;
    }

    /**
     * Parses the interval arguments from a mixed input.
     * The input can be a JSON string representing an array of arguments, a single numeric value, or a simple string.
     * If the input is empty or cannot be parsed, it returns an empty array.
     *
     * @param mixed $args
     * @return array|float[]|int[]|string[]
     */
    private function parseIntervalArgs(mixed $args): array
    {
        if ($args === null || $args === '') {
            return [];
        }

        try {
            $parsedJson = json_decode($args, true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($parsedJson)) {
                return [];
            }

            return $parsedJson;
        } catch (\JsonException) {
            if (is_numeric($args)) {
                if (str_contains($args, '.')) {
                    return [(float)$args];
                }
                return [(int)$args];
            }

            return [(string)$args];
        }
    }

    /**
     * Validates the given interval and its arguments against the available scheduling methods.
     * It checks if the interval is either the special "never" value or a valid method from the ManagesFrequencies trait.
     * If the interval requires arguments, it also checks if the provided arguments meet the required count.
     *
     * @param mixed $interval The interval to validate, which can be a string representing a scheduling method or the special "never" value.
     * @param array $args The arguments to validate against the required parameters of the scheduling method.
     * @param string $command The command being scheduled, used for logging purposes in case of validation failure.
     * @return bool Returns true if the interval and arguments are valid, false otherwise.
     */
    private function validateIntervalAndArgs(mixed $interval, array $args, string $command): bool
    {
        $intervalTraitRef = new \ReflectionClass(ManagesFrequencies::class);
        $intervalMethods = [];
        foreach ($intervalTraitRef->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->class === ManagesFrequencies::class && !in_array($method->name, self::DENIED_INTERVALS, true)) {
                $intervalMethods[$method->name] = $method;
            }
        }

        if (!is_string($interval) || !array_key_exists($interval, $intervalMethods)) {
            $this->logger->error(
                sprintf(
                    'Did not schedule command "%s", because of an invalid interval "%s". Must be one of: %s or "%s".',
                    $command,
                    $interval,
                    implode(', ', array_map(static fn(\ReflectionMethod $method) => $method->name, $intervalMethods)),
                    self::NEVER_INTERVAL
                )
            );
            return false;
        }

        $requiredParameterCount = $intervalMethods[$interval]->getNumberOfRequiredParameters();
        if ($requiredParameterCount > count($args)) {
            $requiredParameterNames = array_map(static fn(\ReflectionParameter $param) => $param->getName(), $intervalMethods[$interval]->getParameters());
            $this->logger->error(
                sprintf(
                    'Did not schedule command "%s", because of missing interval arguments for interval "%s". Required parameters are: %s. Given arguments: %s.',
                    $command,
                    $interval,
                    implode(', ', $requiredParameterNames),
                    json_encode($args)
                )
            );
            return false;
        }

        return true;
    }
}
