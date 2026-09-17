<?php

declare(strict_types=1);

namespace App\Services\Admin\Repositories;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Publishing Center: every assistant instance-wide, regardless of creator or
 * organization, with a derived review/publication status. Fully overrides
 * {@see readContent()} rather than using the generic table-backed
 * `definition()` path (like {@see ProviderRepository}) because the rows need
 * joins (creator name, remix source name, latest version, derived status)
 * a single-table definition can't express, and there is no create/edit form.
 */
class AssistantRepository extends ResourceRepository
{
    public const RESOURCE = 'assistants';

    /** Sortable/columns exposed on the derived row; matches what {@see rows()} selects. */
    private const SORTABLE = ['name', 'handle', 'created_at', 'updated_at', 'version'];

    protected function readContent(User $user, array $filters): array
    {
        $query = $this->rows();

        if (filled($filters['search'] ?? null)) {
            $needle = '%' . mb_strtolower((string) $filters['search']) . '%';
            $query->where(static function (Builder $q) use ($needle): void {
                $q->whereRaw('LOWER(name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(handle) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(creator) LIKE ?', [$needle]);
            });
        }

        foreach (array_filter($filters['where'] ?? [], static fn ($value) => null !== $value && '' !== $value) as $column => $value) {
            match ($column) {
                'status' => $query->where('status', $value),
                'based_on' => $query->where('is_remix', 'remix' === $value ? 1 : 0),
                default => abort(422),
            };
        }

        $sort = $filters['sort'] ?? 'created_at';
        abort_unless(\in_array($sort, self::SORTABLE, true), 422);
        $total = (clone $query)->count();
        $page = (int) ($filters['page'] ?? 1);
        $size = (int) ($filters['size'] ?? 25);
        $direction = $filters['direction'] ?? ('created_at' === $sort ? 'desc' : 'asc');
        // A tie-breaker: 'id' is never itself a sortable column, so this always
        // gives stable pagination ordering for equal values of the primary sort.
        $query->orderBy($sort, $direction)->orderBy('id');

        $rows = $query->offset(($page - 1) * $size)->limit($size)->get()
            ->map(static fn ($row) => [
                'id' => (string) $row->id,
                'name' => $row->name,
                'handle' => $row->handle,
                'creator' => $row->creator,
                'status' => $row->status,
                'version' => null === $row->version ? null : (string) $row->version,
                'based_on' => $row->based_on,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
                // An admin has started reviewing (added flags) but not yet
                // acted on it — surfaced as a "draft" marker on the status
                // column, distinct from the status itself.
                'is_draft' => 'waiting_for_review' === $row->status && (bool) $row->has_unresolved_flags,
            ])->all();

        return [
            'rows' => $rows,
            'columns' => ['name', 'handle', 'creator', 'status', 'version', 'based_on', 'created_at', 'updated_at', 'is_draft'],
            'fields' => [],
            'total' => $total,
            'page' => $page,
            'size' => $size,
            'create' => false,
            'delete' => false,
        ];
    }

    private function rows(): Builder
    {
        $base = DB::table('assistants')
            ->leftJoin('users as creator', 'creator.id', '=', 'assistants.creator_id')
            ->leftJoin('assistants as remixed', 'remixed.id', '=', 'assistants.remixed_assistant_id')
            ->leftJoin('assistant_reviews', 'assistant_reviews.assistant_id', '=', 'assistants.id')
            ->select([
                'assistants.id',
                'assistants.name',
                'assistants.handle',
                'assistants.created_at',
                'assistants.updated_at',
                DB::raw("COALESCE(NULLIF(creator.name, ''), creator.username) as creator"),
                DB::raw('remixed.name as based_on'),
                DB::raw('(CASE WHEN assistants.remixed_assistant_id IS NOT NULL THEN 1 ELSE 0 END) as is_remix'),
                DB::raw('(SELECT MAX(assistant_versions.version) FROM assistant_versions WHERE assistant_versions.assistant_id = assistants.id) as version'),
                DB::raw(<<<'SQL'
                    (CASE WHEN EXISTS (
                        SELECT 1 FROM assistant_field_flags aff
                        WHERE aff.assistant_id = assistants.id AND aff.resolved = 0
                    ) THEN 1 ELSE 0 END) as has_unresolved_flags
                    SQL),
                DB::raw(<<<'SQL'
                    (CASE
                        WHEN assistant_reviews.status = 'blocked' THEN 'blocked'
                        WHEN assistants.requested_release_stage IS NOT NULL THEN 'waiting_for_review'
                        WHEN assistant_reviews.status = 'denied' THEN 'requires_revision'
                        WHEN assistants.release_stage IN ('organizational', 'federated') THEN 'published'
                        ELSE 'private'
                    END) as status
                    SQL),
            ]);

        return DB::query()->fromSub($base, 'admin_assistants');
    }
}
