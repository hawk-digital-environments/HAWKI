<?php
declare(strict_types=1);


namespace App\Services\Ai\ModelInformation\Enrichment;


use App\Services\Ai\ModelInformation\Enrichment\Contracts\ModelInfoEnricherInterface;
use App\Utils\Lists\TopSortList;
use Illuminate\Container\Attributes\Singleton;
use Traversable;

/**
 * Ordered, injectable collection of model-info enrichers.
 *
 * Service providers register {@see ModelInfoEnricherInterface} implementations via
 * {@see register()}, with optional topological ordering constraints (`$before`, `$after`).
 * The pipeline is iterable and yields enrichers in dependency order; the optional fallback
 * enricher is always yielded last, after all registered enrichers.
 *
 * Registration example (in a service provider):
 * ```php
 * $this->app->extend(
 *     AiModelInfoEnrichmentPipeline::class,
 *     fn(AiModelInfoEnrichmentPipeline $pipeline) => $pipeline
 *         ->register($this->app->get(MyPricingEnricher::class))
 *         ->register(
 *             $this->app->get(MyFallbackEnricher::class),
 *             after: [MyPricingEnricher::class]
 *         )
 * );
 * ```
 *
 * @see AiServiceProvider for the built-in registrations.
 */
#[Singleton]
class AiModelInfoEnrichmentPipeline implements \IteratorAggregate
{
    private TopSortList $enrichers;

    private ?ModelInfoEnricherInterface $fallbackEnricher = null;

    public function __construct()
    {
        $this->enrichers = new TopSortList();
    }

    /**
     * Registers an enricher with optional topological ordering constraints.
     *
     * @param array<class-string<ModelInfoEnricherInterface>> $before Class names of enrichers this one must run before.
     * @param array<class-string<ModelInfoEnricherInterface>> $after  Class names of enrichers this one must run after.
     */
    public function register(
        ModelInfoEnricherInterface $enricher,
        array                      $before = [],
        array                      $after = []
    ): self
    {
        $this->enrichers->add($enricher::class, $enricher, $before, $after);

        return $this;
    }

    /**
     * Sets a fallback enricher that is always yielded last, after all registered enrichers.
     *
     * Only one fallback is supported; calling this twice replaces the previous one.
     */
    public function setFallback(
        ModelInfoEnricherInterface $enricher
    ): self
    {
        $this->fallbackEnricher = $enricher;
        return $this;
    }

    /**
     * Yields all registered enrichers in topological order, followed by the fallback (if any).
     */
    public function getIterator(): Traversable
    {
        yield from $this->enrichers;
        if ($this->fallbackEnricher !== null) {
            yield $this->fallbackEnricher::class => $this->fallbackEnricher;
        }
    }
}
