<?php
declare(strict_types=1);


namespace App\Services\AI\Utils;


use App\Services\AI\Interfaces\ClientInterface;

/**
 * A stack of clients, to allow dedicated instance lookup in a nested call context.
 */
class ClientStack implements \IteratorAggregate
{
    /**
     * @var array<int, ClientInterface>
     */
    private array $stack = [];

    /**
     * Push a client (and optionally child clients) onto the stack
     *
     * @param ClientInterface $client
     * @param ClientInterface ...$childClients
     * @return $this
     */
    public function push(ClientInterface $client, ClientInterface ...$childClients): self
    {
        $this->stack = [
            ...$this->stack,
            $client
        ];

        foreach ($childClients as $child) {
            $child->buildStack($this);
        }

        return $this;
    }

    /**
     * Check if a client is already in the stack
     * @param ClientInterface $client
     * @return bool
     */
    public function contains(ClientInterface $client): bool
    {
        return in_array($client, $this->stack, true);
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->stack);
    }
}
