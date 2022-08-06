<?php

namespace MattaDavi\LaravelApiModelServer;

use RuntimeException;
use Illuminate\Foundation\Http\FormRequest;
use MattaDavi\LaravelApiModelServer\Rules\SortRule;
use MattaDavi\LaravelApiModelServer\Rules\QueryTypeRule;

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
    private ApiModelSchema $schemaObject;

    public function __construct()
    {
        $this->setSchemaObject();
    }

    /*
     * Resolve Api model schema object from provided full class name.
     */
    private function setSchemaObject()
    {
        if (! class_exists($this->schema)) {
            throw new RuntimeException('Defined schema does not exist!');
        }

        $schema = app($this->schema);

        if (! $schema instanceof ApiModelSchema) {
            throw new RuntimeException('Schema must be instance of MattaDavi\LaravelApiModelServer\ApiModelSchema');
        }

        $this->schemaObject = $schema;
    }

    public function getSchema(): ApiModelSchema
    {
        return $this->schemaObject;
    }

    public function rules(): array
    {
        return [
            //            'filter' => '',
            'sort' => [
                new SortRule($this->getSchema()),
            ],
            'page' => ['numeric', 'integer'],
            'per_page' => ['numeric', 'integer'],
            //            'nested' => '',
            'queryType' => [
                new QueryTypeRule($this->getSchema()),
            ],
            //            'fields' => '',
            //            'selectRaw' => '',
            'limit' => ['numeric', 'integer'],
            'offset' => ['numeric', 'integer'],
            //            'groupBy' => '',
            //            'compressed' => '',
        ];
    }
}
