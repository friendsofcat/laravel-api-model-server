<?php

namespace MattaDavi\LaravelApiModelServer\Rules;

use Illuminate\Contracts\Validation\Rule;

class SelectRawRule extends BaseSchemaRule implements Rule
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
        $allowedRawClauses = $this->schema->getAllowedRawClauses();

        return $allowedRawClauses === 'all' || in_array('selectRaw', $this->schema->getAllowedRawClauses());
    }

    /**
     * Get the validation error message.
     *
     * @return string|array
     */
    public function message()
    {
        return 'Invalid raw function: selectRaw';
    }
}
