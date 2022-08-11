<?php

namespace MattaDavi\LaravelApiModelServer\Rules;

use Illuminate\Contracts\Validation\Rule;

class FieldsRule extends BaseSchemaRule implements Rule
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

        $values = array_map(
            fn ($value) => $value['value'],
            $this->schema->getParser()->parseFieldsValues($value)
        );

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
        return sprintf('Invalid select attribute: %s', $this->errorValue);
    }

    public function isValidValue($value, $allowedValues): bool
    {
        return in_array($value, $this->schema->getAttributeAliases()) || $this->isAllowed($value, $allowedValues);
    }
}
