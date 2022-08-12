<?php

namespace MattaDavi\LaravelApiModelServer\Rules;

use http\Params;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Log;
class FieldsRule extends BaseSchemaRule implements Rule
{
    public $model;
    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $this->model = $this->schema->getModel();
        $allowedAttributes = $this->schema->getAllowedAttributes();

        if ($this->shouldAllowEverything($allowedAttributes)) {
            return true;
        }

        $values = array_map(
            fn ($value) => $value['value'],
            $this->schema->getParser()->parseFieldsValues($value)
        );

        foreach ($values as $value) {
            if (! $this->isValidTable($value)) {
                return false;
            }

            if ($value != '*' && ! $this->isValidValue($value, $allowedAttributes)) {
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
        return sprintf('Invalid select attribute: %s', $this->errorValue);
    }

    // todo refactor
    public function isValidValue($value, $allowedValues): bool
    {
        $parts = explode('.', $value);
        $table = count($parts) > 1
            ? $parts[0]
            : null;

        $this->errorValue = $value;

        if (! is_null($table) && $table != $this->model->getTable()) {
            return true;
        }

        if (is_null($table) || $table == $this->model->getTable()) {
            if (isset($parts[1]) && $parts[1] == '*') {
                return true;
            }

            if (in_array($parts[1] ?? $value, $this->schema->getAttributeAliases())
                || $this->isAllowed($parts[1] ?? $value, $allowedValues)) {
                return true;
            }
        }

        return true;
    }

    public function isValidTable(string $value): bool
    {
        $parts = explode('.', $value);
        $table = count($parts) > 1
            ? $parts[0]
            : null;

        if (! is_null($table)) {
            $allowedEagerLoadsWithTable = $this->schema->getAllowedEagerLoadsWithTable();
            $allowedTables = array_map(
                fn ($relation) => $relation['table'],
                $allowedEagerLoadsWithTable
            );

            $this->errorValue = $value;

            return in_array($table, [$this->model->getTable(), ...$allowedTables])
                && $this->isValidColumn($table, $parts[1], $allowedEagerLoadsWithTable);
        }

        return true;
    }

    public function isValidColumn(string $table, string $column, array $allowedData): bool
    {
        if ($column == '*' || $table == $this->model->getTable()) {
            return true;
        }

        $relation = [];

        foreach ($allowedData as $data) {
            if ($data['table'] == $table) {
                $relation = $data;
                break;
            }
        }

        return ! empty($relation) && (empty($relation['columns']) || in_array($column, $relation['columns']));
    }
}
