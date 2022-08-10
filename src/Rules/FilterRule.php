<?php

namespace MattaDavi\LaravelApiModelServer\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Support\Facades\Log;
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
        $formattedFilters = $this->schema->getParser()->parseFilterValues($value);
//        if ($this->shouldAllowEverything($allowedAttributes)) {
//            return true;
//        }
//
//        $values = $this->schema->parseFieldsValues($value);
//
//        return $this->isEverythingAllowed($values, $allowedAttributes);
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
}
