<?php
declare(strict_types=1);


namespace App\Services\Ai\Exceptions;

/**
 * Marker interface for all exceptions thrown within the AI domain.
 *
 * Catch this interface at call sites to handle any AI-related failure
 * regardless of the concrete exception type:
 *
 * ```php
 * try {
 *     $proxy = $resolver->resolve($provider);
 * } catch (AiExceptionInterface $e) {
 *     // handles ProviderNotFoundException, InvalidProviderConfigurationException, etc.
 * }
 * ```
 */
interface AiExceptionInterface extends \Throwable
{

}
