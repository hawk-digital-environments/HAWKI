<?php

declare(strict_types=1);

namespace App\Orchid\Filters;

use Illuminate\Database\Eloquent\Builder;
use Orchid\Filters\Filter;
use Orchid\Screen\Fields\Select;

class SystemTextStatusFilter extends Filter
{
    /**
     * The displayable name of the filter.
     */
    public function name(): string
    {
        return 'Status';
    }

    /**
     * The array of matched parameters.
     */
    public function parameters(): array
    {
        return ['status'];
    }

    /**
     * Apply to a given Eloquent query builder.
     */
    public function run(Builder $builder): Builder
    {
        $status = $this->request->get('status');

        if (empty($status)) {
            return $builder;
        }

        switch ($status) {
            case 'complete':
                // Both German and English content exist
                return $builder->whereExists(function ($query) {
                    $query->selectRaw(1)
                        ->from('app_system_texts as ast2')
                        ->whereColumn('ast2.content_key', 'app_system_texts.content_key')
                        ->where('ast2.language', 'de_DE')
                        ->where('ast2.content', '!=', '');
                })->whereExists(function ($query) {
                    $query->selectRaw(1)
                        ->from('app_system_texts as ast3')
                        ->whereColumn('ast3.content_key', 'app_system_texts.content_key')
                        ->where('ast3.language', 'en_US')
                        ->where('ast3.content', '!=', '');
                });

            case 'partial':
                // Only one language has content
                return $builder->where(function ($query) {
                    $query->where(function ($subQuery) {
                        // Has German but not English
                        $subQuery->where('language', 'de_DE')
                            ->where('content', '!=', '')
                            ->whereNotExists(function ($notExistsQuery) {
                                $notExistsQuery->selectRaw(1)
                                    ->from('app_system_texts as ast2')
                                    ->whereColumn('ast2.content_key', 'app_system_texts.content_key')
                                    ->where('ast2.language', 'en_US')
                                    ->where('ast2.content', '!=', '');
                            });
                    })->orWhere(function ($subQuery) {
                        // Has English but not German
                        $subQuery->where('language', 'en_US')
                            ->where('content', '!=', '')
                            ->whereNotExists(function ($notExistsQuery) {
                                $notExistsQuery->selectRaw(1)
                                    ->from('app_system_texts as ast2')
                                    ->whereColumn('ast2.content_key', 'app_system_texts.content_key')
                                    ->where('ast2.language', 'de_DE')
                                    ->where('ast2.content', '!=', '');
                            });
                    });
                });

            case 'empty':
                // No content in either language
                return $builder->where('content', '');

            default:
                return $builder;
        }
    }

    /**
     * The displayable fields.
     */
    public function display(): iterable
    {
        return [
            Select::make('status')
                ->title('Status')
                ->empty('All Status')
                ->options([
                    'complete' => 'Complete (Both languages)',
                    'partial' => 'Partial (One language)',
                    'empty' => 'Empty (No content)',
                ])
                ->value($this->request->get('status')),
        ];
    }
}
