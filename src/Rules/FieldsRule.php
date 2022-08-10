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

        $values = $this->schema->getParser()->parseFieldsValues($value);

        return $this->isEverythingAllowed($values, $allowedAttributes);
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
}
