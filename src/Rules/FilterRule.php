<?php

namespace MattaDavi\LaravelApiModelServer\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\DataAwareRule;

class FilterRule extends BaseSchemaRule implements DataAwareRule, Rule
{
    /**
     * All of the data under validation.
     *
     * @var array
     */
    protected $data = [];

    /**
     * Set the data under validation.
     *
     * @param  array  $data
     * @return $this
     */
    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $allowedAttributes = $this->schema->getAllowedAttributes();
        $allowedScopes = $this->schema->getAllowedScopes();
        $formattedFilters = $this->parser->parseFilterValues($value);
        $formattedNestings = isset($this->data['nested'])
            ? $this->parser->parseNestedValues($this->data['nested'])
            : [];

        foreach ($formattedFilters as $key => $filter) {
            if (! $this->isValidType($filter)) {
                return false;
            }

            if (! $this->hasValidNesting($filter, $formattedNestings)) {
                return false;
            }

            if (! $this->hasValidColumn($filter, $allowedAttributes)) {
                return false;
            }

            if (! $this->isValidWhereColumn($filter, $allowedAttributes)) {
                return false;
            }

            if (! $this->isValidScope($filter, $allowedScopes)) {
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
        return sprintf('Invalid filter used: %s', $this->errorValue);
    }

    public function hasValidNesting(array $filter, array $nestings): bool
    {
        $this->errorValue = 'nesting';

        return $filter['nested'] == -1
            || ($filter['nested'] > -1 && isset($nestings[$filter['nested']]));
    }

    public function hasValidColumn(array $filter, array|string $allowedAttributes): bool
    {
        if (in_array($filter['type'], ['raw', 'Scope', 'Column'])) {
            return true;
        }

        return $this->shouldAllowEverything($allowedAttributes)
            || $this->isAllowed($this->parser->parseFieldValue($filter['column'])['value'], $allowedAttributes);
    }

    public function isValidWhereColumn(array $filter, array|string $allowedAttributes): bool
    {
        if ($filter['type'] != 'Column') {
            return true;
        }

        $firstColumnData = explode('.', $filter['first']);
        $first = [
            'table' => isset($firstColumnData[1]) ? $firstColumnData[0] : null,
            'column' => $firstColumnData[1] ?? $firstColumnData[0],
        ];

        $secondColumnData = explode('.', $filter['second']);
        $second = [
            'table' => isset($secondColumnData[1]) ? $secondColumnData[0] : null,
            'column' => $secondColumnData[1] ?? $secondColumnData[0],
        ];

        foreach ([$first, $second] as $columnData) {
            if ($this->isBaseTable($columnData['column'])
                && ! $this->isAllowed($this->parser->parseFieldValue($columnData['column'])['value'], $allowedAttributes)) {
                return false;
            }

            if ($columnData['table'] != $this->model->getTable()) {
                $tableColumn = implode('.', [$columnData['table'], $columnData['column']]);

                return $this->isValidTable($tableColumn);
            }
        }

        return true;
    }

    public function isValidScope(array $filter, array|string $allowedScopes): bool
    {
        if ($filter['type'] != 'Scope') {
            return true;
        }

        $this->errorValue = sprintf('externalScope(%s)', $filter['scope']);

        return $this->shouldAllowEverything($allowedScopes)
            || isset($this->parser->scopeAliases[$filter['scope']])
            || $this->isAllowed($filter['scope'], $allowedScopes);
    }

    public function isValidType(array $filter): bool
    {
        if ($this->schema->getAllowedRawClauses() == 'all') {
            return true;
        }

        if (in_array($filter['type'], ['raw', 'InRaw', 'NotInRaw'])) {
            $this->errorValue = $filter['type'];
            $formattedType = $filter['type'] == 'raw'
                ? 'whereRaw'
                : 'whereInteger' . $filter['type'];

            return in_array($formattedType, $this->schema->getAllowedRawClauses());
        }

        return true;
    }

    public function isBaseTable(?string $table): bool
    {
        return is_null($table) || $table == $this->model->getTable();
    }
}
