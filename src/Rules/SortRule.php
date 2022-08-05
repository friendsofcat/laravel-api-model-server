<?php

namespace MattaDavi\LaravelApiModelServer\Rules;

use Illuminate\Contracts\Validation\Rule;
use MattaDavi\LaravelApiModelServer\ApiModelSchema;

class SortRule implements Rule
{
    public string $currentValue;

    public array|string $allowedAttributes;

    public string $arrayValueSeparator = ',';

    public function __construct(public ApiModelSchema $schema)
    {
        $this->allowedAttributes = $schema->getAllowedAttributes();
        $this->arrayValueSeparator = $schema::ARRAY_VALUE_SEPARATOR;
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
        if ($this->allowedAttributes === 'all') {
            return true;
        }

        $values = explode($this->arrayValueSeparator, $value);

        foreach ($values as $value) {
            $formattedValue = ltrim($value, '-');
            $this->currentValue = $formattedValue;

            if ($this->allowedAttributes['mode'] === 'restricted'
                && in_array($formattedValue, $this->allowedAttributes['values'])) {
                return false;
            } elseif ($this->allowedAttributes['mode'] === 'allowed'
                && ! in_array($formattedValue, $this->allowedAttributes['values'])) {
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
        return sprintf('Cannot order by restricted attribute: %s', $this->currentValue);
    }
}
