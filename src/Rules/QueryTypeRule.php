<?php

namespace FriendsOfCat\LaravelApiModelServer\Rules;

use Illuminate\Contracts\Validation\Rule;
use FriendsOfCat\LaravelApiModelServer\ApiModelSchema;

class QueryTypeRule extends BaseSchemaRule implements Rule
{
    public function __construct(public ApiModelSchema $schema, public $requestMethod)
    {
        $this->model = $schema->getModel();
        $this->parser = $schema->getParser();
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
        $allowedMethods = $this->schema->getAllowedMethods();
        $values = $this->parser->parseQueryTypeValues($value);

        if (! empty($values['args'])) {
            $allowedAttributes = $this->schema->getAllowedAttributes();

            return $this->isAllowed($values['method'], $allowedMethods)
                && $this->areAllowedArgs($values['args'], $allowedAttributes);
        }

        return $this->isAllowed($values['method'], $allowedMethods) && $this->isAllowedRequestType($values['method']);
    }

    /**
     * Get the validation error message.
     *
     * @return string|array
     */
    public function message()
    {
        return sprintf('Invalid queryType or queryType attribute. %s', $this->errorValue);
    }

    public function areAllowedArgs($values, $allowedAttributes): bool
    {
        return $this->shouldAllowEverything($allowedAttributes)
            || $this->isEverythingAllowed($values, $allowedAttributes);
    }

    public function isAllowedRequestType($method)
    {
        $this->errorValue = 'Unsupported request type for this action: ' . $this->requestMethod;

        return in_array($method, $this->schema->allowedRequestTypeMethod[$this->requestMethod]);
    }
}
