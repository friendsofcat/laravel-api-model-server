<?php

namespace MattaDavi\LaravelApiModelServer\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\DataAwareRule;

class FilterRule extends BaseSchemaRule implements DataAwareRule, Rule
{
    public $parser;
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
        $this->parser = $this->schema->getParser();
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

    public function hasValidColumn(array $filter, array $allowedAttributes): bool
    {
        if (in_array($filter['type'], ['raw', 'Scope']) || in_array($filter['column'], ['in_raw', 'not_in_raw'])) {
            return true;
        }

        return $this->shouldAllowEverything($allowedAttributes)
            || $this->isAllowed($this->parser->parseFieldValue($filter['column'])['value'], $allowedAttributes);
    }

    public function isValidWhereColumn(array $filter, array $allowedAttributes): bool
    {
        if ($filter['type'] != 'Column') {
            return true;
        }

        $firstColumnData = explode('.', $filter['first']);
        $first = [
            'table' => isset($firstColumnData[1]) ? null : $firstColumnData[0],
            'column' => $firstColumnData[1] ?? $firstColumnData[0],
        ];

        $secondColumnData = explode('.', $filter['first']);
        $second = [
            'table' => isset($secondColumnData[1]) ? null : $secondColumnData[0],
            'column' => $secondColumnData[1] ?? $secondColumnData[0],
        ];

        $model = $this->schema->getModel();

        foreach ([$first, $second] as $columnData) {
            if ((is_null($columnData['table']) || $columnData['table'] == $model->getTable())
                && ! $this->isAllowed($this->parser->parseFieldValue($columnData['column'])['value'], $allowedAttributes)) {
                return false;
            }

            if ($columnData['table'] != $model->getTable()) {
                // todo: Handle same origin whereHas
                return false;
            }
        }

        return true;
    }

    public function isValidScope(array $filter, array $allowedScopes): bool
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

        if ($filter['type'] == 'raw') {
            $this->errorValue = 'whereRaw';

            return in_array('whereRaw', $this->schema->getAllowedRawClauses());
        }

        if (isset($filter['column']) && in_array($filter['column'], ['in_raw', 'not_in_raw'])) {
            $formattedColumn = 'where' . implode('', array_map(
                fn ($value) => ucfirst($value),
                explode('_', $filter['column'])
            ));

            $this->errorValue = $formattedColumn;

            return in_array($formattedColumn, $this->schema->getAllowedRawClauses());
        }

        return true;
    }
}
