<?php
declare(strict_types=1);


namespace App\Models\Scopes\Generic;


use App\Models\Scopes\Traits\UsageTypeAwareScopeTrait;
use App\Services\System\UsageTypes\Contracts\WellKnownUsageTypes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Scope that applies usage-type overlay logic for non-default usage types.
 *
 * The overlay pattern merges two sets of records for a given request:
 * - All records explicitly assigned to the current (non-default) usage type.
 * - Records from the default usage type that have no corresponding override in the
 *   current usage type, identified by the discriminator fields.
 *
 * This allows a non-default usage type to inherit the defaults and selectively override them.
 * When the current usage type equals the default, only default records are returned (no overlay).
 *
 * Example — for `usage_type = 'ext_app_1'` with `discriminatorFields = 'model_type'`:
 * ```sql
 * WHERE usage_type = 'ext_app_1'
 * OR (
 *     usage_type = 'main_app'
 *     AND NOT EXISTS (
 *         SELECT 1 FROM <table> AS _overlay
 *         WHERE _overlay.usage_type = 'ext_app_1'
 *           AND _overlay.model_type = <table>.model_type
 *     )
 * )
 * ```
 */
class UsageTypeOverlayScope implements Scope
{
    use UsageTypeAwareScopeTrait;

    /**
     * @param string|array $discriminatorFields Column(s) that uniquely identify a logical record
     *   across usage types. Used in the NOT EXISTS sub-select to detect whether a default record
     *   already has an override in the current usage type.
     * @param string $fieldName Column holding the usage type (default `usage_type`).
     * @param string $defaultUsageType The baseline usage type to fall back to
     *   (default: {@see WellKnownUsageTypes::MAIN_APP}).
     */
    public function __construct(
        private readonly string|array $discriminatorFields,
        private readonly string       $fieldName = 'usage_type',
        private readonly string       $defaultUsageType = WellKnownUsageTypes::MAIN_APP
    )
    {
    }

    public function apply(Builder $builder, Model $model): void
    {
        $usageType = $this->getCurrentUsageType();

        // If in the default usage type context, do not apply the overlay and return all records for that usage type.
        if ($usageType === $this->defaultUsageType) {
            $builder->where($this->fieldName, $usageType);
            return;
        }

        // For non-default usage types, apply the overlay logic: show all records for the current usage type,
        // plus any default-usage-type records where no override exists for the same discriminator field combination.
        $tableName = $builder->getModel()->getTable();
        $discriminatorFields = array_unique(is_array($this->discriminatorFields)
            ? $this->discriminatorFields
            : [$this->discriminatorFields]);

        $builder->where(function (Builder $query) use ($usageType, $tableName, $discriminatorFields) {
            $query->where($this->fieldName, $usageType)
                ->orWhere(function (Builder $q) use ($usageType, $tableName, $discriminatorFields) {
                    $q->where($this->fieldName, $this->defaultUsageType)
                        ->whereNotExists(function ($sub) use ($usageType, $tableName, $discriminatorFields) {
                            $sub->selectRaw('1')
                                ->from($tableName . ' as _overlay')
                                ->where('_overlay.' . $this->fieldName, $usageType);
                            foreach ($discriminatorFields as $field) {
                                $sub->whereColumn('_overlay.' . $field, $tableName . '.' . $field);
                            }
                        });
                });
        });
    }
}
