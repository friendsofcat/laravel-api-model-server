<?php

namespace MattaDavi\LaravelApiModelServer;

use RuntimeException;
use Illuminate\Foundation\Http\FormRequest;
use MattaDavi\LaravelApiModelServer\Rules\SortRule;

abstract class ApiRequest extends FormRequest
{
    /*
     * Full namespace of Api model schema class.
     */
    protected string $schema;

    /*
     * Api model schema object resolved from $schema.
     * Holds necessary info for validation.
     */
    protected ApiModelSchema $schemaObject;

    public function __construct()
    {
        $this->schemaObject = $this->getSchema();
    }

    /*
     * Resolve Api model schema object from provided full class name.
     */
    protected function getSchema()
    {
        if (! class_exists($this->schema)) {
            throw new RuntimeException('Defined schema does not exist!');
        }

        $schema = app($this->schema);

        if (! $schema instanceof ApiModelSchema) {
            throw new RuntimeException('Schema should be instance of MattaDavi\LaravelApiModelServer\ApiModelSchema');
        }

        return $schema;
    }

    public function rules(): array
    {
        return [
            //            'filter' => '',
            'sort' => [
                new SortRule($this->schemaObject),
            ],
            'page' => ['numeric', 'integer'],
            'per_page' => ['numeric', 'integer'],
            //            'nested' => '',
            //            'queryType' => '',
            //            'fields' => '',
            //            'selectRaw' => '',
            'limit' => ['numeric', 'integer'],
            'offset' => ['numeric', 'integer'],
            //            'groupBy' => '',
            //            'compressed' => '',
        ];
    }
}
