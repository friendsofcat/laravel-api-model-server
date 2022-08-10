<?php

namespace MattaDavi\LaravelApiModelServer\Rules;

use Illuminate\Contracts\Validation\Rule;

class NestedRule extends BaseSchemaRule implements Rule
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
        $nestings = $this->schema->getParser()->parseNestedValues($value);

        foreach ($nestings as $nesting) {
            if (! $this->isValidNesting($nesting, $nestings)) {
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
        return 'Invalid Nested legend.';
    }

    public function hasValidParent($nesting, $nestings): bool
    {
        $parent = $nesting['parts']['parent'];

        return is_null($parent) || (is_numeric($parent) && isset($nestings[(int) $parent]));
    }

    public function hasValidBoolean($nesting): bool
    {
        return in_array($nesting['parts']['boolean'], ['and', 'or']);
    }

    public function hasValidMethod($nesting): bool
    {
        $method = $nesting['parts']['method'];

        return is_null($method) || in_array($method, array_keys($this->schema::NESTED_METHODS));
    }

    public function isValidNesting($nesting, $nestings): bool
    {
        return $this->hasValidParent($nesting, $nestings)
            && $this->hasValidBoolean($nesting)
            && $this->hasValidMethod($nesting);
    }
}
