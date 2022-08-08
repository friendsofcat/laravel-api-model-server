<?php

namespace MattaDavi\LaravelApiModelServer\Rules;

use Illuminate\Contracts\Validation\Rule;

class EagerLoadRule extends BaseSchemaRule implements Rule
{
    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $allowedEagerLoads = $this->schema->getAllowedEagerLoads();

        if ($allowedEagerLoads === 'all') {
            return true;
        }

        foreach ($this->schema->parseIncludeValues($value) as $relation => $columns) {
            if (! $this->isValidEagerLoad($relation, $columns, $allowedEagerLoads)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string|array
     */
    public function message()
    {
        return 'Invalid relation or relation column used.';
    }

    protected function isValidEagerLoad($relation, $columns, $allowedValues): bool
    {
        return $this->isValidRelation($relation, $allowedValues) && $this->isUsingValidColumns($relation, $columns, $allowedValues);
    }

    protected function isValidRelation($value, $allowedValues): bool
    {
        return isset($allowedValues[$value]);
    }

    protected function isUsingValidColumns($relation, $columns, $allowedValues): bool
    {
        if (! $this->isValidRelation($relation, $allowedValues)) {
            return false;
        }

        foreach ($columns as $column) {
            if (! in_array($column, $allowedValues[$relation])) {
                return false;
            }
        }

        return true;
    }
}
