<?php

namespace FriendsOfCat\LaravelApiModelServer;

use RuntimeException;
use Illuminate\Foundation\Http\FormRequest;
use FriendsOfCat\LaravelApiModelServer\Rules\SortRule;
use FriendsOfCat\LaravelApiModelServer\Rules\FieldsRule;
use FriendsOfCat\LaravelApiModelServer\Rules\FilterRule;
use FriendsOfCat\LaravelApiModelServer\Rules\NestedRule;
use FriendsOfCat\LaravelApiModelServer\Rules\GroupByRule;
use FriendsOfCat\LaravelApiModelServer\Rules\EagerLoadRule;
use FriendsOfCat\LaravelApiModelServer\Rules\QueryTypeRule;
use FriendsOfCat\LaravelApiModelServer\Rules\SelectRawRule;

abstract class ApiRequest extends FormRequest
{
    protected $stopOnFirstFailure = true;

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
            throw new RuntimeException('Schema must be instance of ' . ApiModelSchema::class);
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
            'filter' => [
                new FilterRule($this->getSchema()),
            ],
            'sort' => [
                new SortRule($this->getSchema()),
            ],
            'page' => ['numeric', 'integer'],
            'per_page' => ['numeric', 'integer'],
            'nested' => [
                new NestedRule($this->getSchema()),
            ],
            'queryType' => [
                new QueryTypeRule($this->getSchema(), $this->method()),
            ],
            'fields' => [
                new FieldsRule($this->getSchema()),
            ],
            'include' => [
                new EagerLoadRule($this->getSchema()),
            ],
            'selectRaw' => [
                new SelectRawRule($this->getSchema()),
            ],
            'limit' => ['numeric', 'integer'],
            'offset' => ['numeric', 'integer'],
            'groupBy' => [
                new GroupByRule($this->getSchema()),
            ],
            //            'compressed' => '',
        ];
    }
}
