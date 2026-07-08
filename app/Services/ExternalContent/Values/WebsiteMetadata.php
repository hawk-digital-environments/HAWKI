<?php
declare(strict_types=1);


namespace App\Services\ExternalContent\Values;


use Illuminate\Contracts\Support\Arrayable;

readonly class WebsiteMetadata implements Arrayable, \JsonSerializable
{
    public function __construct(
        /**
         * The full, given url of the page
         */
        public string  $url,
        /**
         * The extracted domain of the page, e.g. example.com
         */
        public string  $domain,
        /**
         * The extracted title of the page, if any
         */
        public string  $title,
        /**
         * The extracted description of the page, if any
         */
        public ?string $description = null,
        /**
         * The URL of the extracted image of the page, if any
         * This is ALWAYS an internal HAWKI url, which will be proxied through the ExternalImageProxy service
         */
        public ?string $image = null,
        /**
         * The URL of the extracted favicon of the page, if any
         * This is ALWAYS an internal HAWKI url, which will be proxied through the ExternalImageProxy service
         */
        public ?string $favicon = null,
        /**
         * True if the metadata was generated for a failed request and is not really extracted from the page.
         */
        public bool    $isFallback = false
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'domain' => $this->domain,
            'title' => $this->title,
            'description' => $this->description,
            'image' => $this->image,
            'favicon' => $this->favicon,
            'isFallback' => $this->isFallback,
        ];
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
