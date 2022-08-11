<?php

namespace MattaDavi\LaravelApiModelServer\Rules;

use MattaDavi\LaravelApiModelServer\ApiModelSchema;
use MattaDavi\LaravelApiModelServer\Concerns\WorksWithRestrictedValues;

abstract class BaseSchemaRule
{
    use WorksWithRestrictedValues;

    public function __construct(public ApiModelSchema $schema)
    {
    }

    public function getClientAliases(): array
    {
        $aliases = [];

        if (isset($this->data) && isset($this->data['fields'])) {
            $aliases = array_map(
                fn ($value) => $value['alias'],
                $this->schema->getParser()->parseFieldsValues($this->data['fields'])
            );
        }

        return $aliases;
    }
}
