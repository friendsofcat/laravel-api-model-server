<?php

namespace FriendsOfCat\LaravelApiModelServer\Rules;

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

        foreach ($this->parser->parseIncludeValues($value) as $relation) {
            if (! $this->isValidEagerLoad($relation['relation'], $relation['columns'], $allowedEagerLoads)) {
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
        return (bool) sizeof($this->getRelationByName($value, $allowedValues));
    }

    protected function isUsingValidColumns($relation, $columns, $allowedValues): bool
    {
        if (! $this->isValidRelation($relation, $allowedValues)) {
            return false;
        }

        $relation = $this->getRelationByName($relation, $allowedValues);

        if (empty($relation['columns'])) {
            return true;
        }

        foreach ($columns as $column) {
            if (! in_array($column, $relation['columns'])) {
                return false;
            }
        }

        return true;
    }

    protected function getRelationByName($relation, $values)
    {
        foreach ($values as $value) {
            if ($value['relation'] == $relation) {
                return $value;
            }
        }

        return [];
    }
}
