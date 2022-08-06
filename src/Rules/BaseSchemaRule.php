<?php

namespace MattaDavi\LaravelApiModelServer\Rules;

use MattaDavi\LaravelApiModelServer\ApiModelSchema;
use MattaDavi\LaravelApiModelServer\Concerns\WorksWithRestrictedValues;

abstract class BaseSchemaRule
{
    use WorksWithRestrictedValues;

    public function __construct(public ApiModelSchema $schema)
    {}
}
