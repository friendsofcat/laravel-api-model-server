<?php

namespace MattaDavi\LaravelApiModelServer\Rules;

use Illuminate\Contracts\Validation\Rule;

class QueryTypeRule extends BaseSchemaRule implements Rule
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
        $allowedMethods = $this->schema->getAllowedMethods();
        $values = $this->schema->getParser()->parseQueryTypeValues($value);

        if (! empty($values['args'])) {
            $allowedAttributes = $this->schema->getAllowedAttributes();

            return $this->isAllowed($values['method'], $allowedMethods)
                && $this->isEverythingAllowed($values['args'], $allowedAttributes);
        }

        return $this->isAllowed($values['method'], $allowedMethods);
    }

    /**
     * Get the validation error message.
     *
     * @return string|array
     */
    public function message()
    {
        return sprintf('Invalid queryType or queryType attribute: %s', $this->errorValue);
    }
}
