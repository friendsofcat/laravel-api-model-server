<?php

namespace MattaDavi\LaravelApiModelServer\Rules;

use Illuminate\Contracts\Validation\Rule;

class GroupByRule extends BaseSchemaRule implements Rule
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
        $allowedAttributes = $this->schema->getAllowedAttributes();

        if ($this->shouldAllowEverything($allowedAttributes)) {
            return true;
        }

        $values = $this->schema->parseGroupByValues($value);

        foreach ($values as $value) {
            if (! $this->isValidValue($value, $allowedAttributes)) {
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
        return sprintf('Invalid groupBy attribute: %s', $this->errorValue);
    }

    public function isValidValue($value, $allowedValues): bool
    {
        return isset($this->schema->getAttributeAliases()[$value]) || $this->isAllowed($value, $allowedValues);
    }
}
