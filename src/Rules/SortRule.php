<?php

namespace MattaDavi\LaravelApiModelServer\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\DataAwareRule;

class SortRule extends BaseSchemaRule implements DataAwareRule, Rule
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

        if ($this->shouldAllowEverything($allowedAttributes)) {
            return true;
        }

        $values = array_map(
            fn ($value) => $value['value'],
            $this->schema->getParser()->parseSortValues($value)
        );
        $clientAliases = $this->getClientAliases();

        foreach ($values as $value) {
            if (! $this->isValidValue($value, $allowedAttributes, $clientAliases)) {
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
        return sprintf('Cannot order by restricted attribute: %s', $this->errorValue);
    }

    public function isValidValue($value, $allowedValues, $clientAliases = []): bool
    {
        $this->errorValue = $value;

        return isset($this->schema->getAttributeAliases()[$value])
            || in_array($value, $clientAliases)
            || $this->isAllowed($value, $allowedValues);
    }
}
