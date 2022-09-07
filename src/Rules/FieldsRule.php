<?php

namespace FriendsOfCat\LaravelApiModelServer\Rules;

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
            $this->parser->parseFieldsValues($value)
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

    public function isValidValue($value, $allowedValues): bool
    {
        $parts = explode('.', $value);
        $table = count($parts) > 1
            ? $parts[0]
            : null;

        $this->errorValue = $value;
        $modelTable = $this->model->getTable();

        if (! is_null($table) && $table != $modelTable) {
            return true;
        }

        if (isset($parts[1]) && $parts[1] == '*') {
            return true;
        }

        if (in_array($parts[1] ?? $value, $this->schema->getAttributeAliases())
            || $this->isAllowed($parts[1] ?? $value, $allowedValues)) {
            return true;
        }

        return false;
    }
}
