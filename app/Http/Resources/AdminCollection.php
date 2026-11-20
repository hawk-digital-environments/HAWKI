<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/** JSON:API collection of the records visible to an administrator in one section. */
final readonly class AdminCollection implements Responsable
{
    public function __construct(private string $section, private array $content)
    {
    }

    /** @param Request $request */
    public function toResponse($request): JsonResponse
    {
        $data = array_map(fn (array $row) => new AdminResource($this->section, $row), $this->content['rows']);
        $meta = Arr::except($this->content, ['rows', 'page', 'size', 'total']);
        $links = ['self' => $request->fullUrl()];

        if (isset($this->content['total'])) {
            $page = $this->content['page'];
            $size = $this->content['size'];
            $last = max(1, (int) ceil($this->content['total'] / $size));
            $meta['page'] = ['currentPage' => $page, 'perPage' => $size, 'total' => $this->content['total'], 'lastPage' => $last];
            $pageUrl = static fn (int $number) => $request->url() . '?' . http_build_query(
                array_replace($request->query(), ['page' => ['number' => $number, 'size' => $size]])
            );
            $links += [
                'first' => $pageUrl(1),
                'last' => $pageUrl($last),
                'prev' => $page > 1 ? $pageUrl($page - 1) : null,
                'next' => $page < $last ? $pageUrl($page + 1) : null,
            ];
        }

        return response()->json(['data' => $data, 'meta' => (object) $meta, 'links' => $links], 200, [
            'Content-Type' => 'application/vnd.api+json',
        ]);
    }
}
