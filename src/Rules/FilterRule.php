<?php

namespace MattaDavi\LaravelApiModelServer\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Support\Facades\Log;
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
        $formattedFilters = $this->parser->parseFilterValues($value);
        $formattedNestings = isset($this->data['nested'])
            ? $this->parser->parseNestedValues($this->data['nested'])
            : [];

        foreach ($formattedFilters as $key => $filter) {
            if (! $this->hasValidNesting($filter, $formattedNestings)) {
                return false;
            }

            if (! $this->hasValidColumn($filter, $allowedAttributes)) {
                return false;
            }

            if (! $this->isValidWhereColumn($filter, $allowedAttributes)) {
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
            'column' => $firstColumnData[1] ?? $firstColumnData[0]
        ];

        $secondColumnData = explode('.', $filter['first']);
        $second = [
            'table' => isset($secondColumnData[1]) ? null : $secondColumnData[0],
            'column' => $secondColumnData[1] ?? $secondColumnData[0]
        ];

        $model = $this->schema->getModel();

        foreach ([$first, $second] as $columnData) {
            if ((is_null($columnData['table']) || $columnData['table'] == $model->getTable())
                && ! $this->isAllowed($this->parser->parseFieldValue($columnData['column'])['value'], $allowedAttributes)) {
                return false;
            }

            if ($columnData['table'] != $model->getTable()) {
                return false;
            }
        }

        return true;
    }
}
