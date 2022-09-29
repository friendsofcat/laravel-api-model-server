<?php

namespace FriendsOfCat\LaravelApiModelServer;

use RuntimeException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
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

    /**
     * Prepare the data for validation.
     * We should retrieve only allowed attributes as defined in Schema.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        if ($this->shouldOverwriteFields()) {
            $this->merge([
                'fields' => $this->setDefaultFields(),
            ]);
        }

        if (isset($this->include)) {
            $this->merge([
                'include' => $this->prepareInclude(),
            ]);
        }
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json($validator->errors(), 422));
    }

    protected function shouldOverwriteFields(): bool
    {
        if ($this->getSchema()->getAllowedAttributes() == 'all') {
            return false;
        }

        if (! isset($this->fields) && $this->getSchema()->getAllowedAttributes() != 'all') {
            return true;
        }

        $fields = explode($this->getSchema()->getParser()::ARRAY_VALUE_SEPARATOR, $this->fields);

        return ! empty(array_filter(
            $fields,
            fn ($field) => str_contains($field, '*')
        ));
    }

    protected function setDefaultFields()
    {
        $allowedAttributes = $this->getSchema()->getAllowedAttributes();

        $fields = $allowedAttributes['values'];

        // To have the ability to include everything but restricted columns,
        // we need to retrieve all column names from DB and exclude restricted columns.
        // NOTE: This will create an extra query. Result will be cached for 24h.
        if ($allowedAttributes['mode'] == 'restricted') {
            $model = $this->getSchema()->getModel();
            $key = 'laravel-api-model-server|' . $model->getConnection()->getDatabaseName() . '|columns';
            $modelColumns = Cache::remember(
                $key,
                60 * 60 * 24,
                fn () => $model->getConnection()->getSchemaBuilder()->getColumnListing($model->getTable())
            );

            $fields = array_filter(
                $modelColumns,
                fn ($column) => ! in_array($column, $allowedAttributes['values'])
            );
        }

        // Include aliased columns in response
        foreach ($this->getSchema()->getAttributeAliases() as $alias => $column) {
            $fields[] = $alias;
        }

        // If request wants to retrieve some custom fields (like aliased columns) but also everything else,
        // we need to overwrite this request to retrieve only allowed attributes defined within schema.
        if (isset($this->fields)) {
            $fields = array_merge($fields, array_filter(
                explode($this->getSchema()->getParser()::ARRAY_VALUE_SEPARATOR, $this->fields),
                fn ($field) => ! str_contains($field, '*')
            ));
        }

        return implode($this->getSchema()->getParser()::ARRAY_VALUE_SEPARATOR, $fields);
    }

    /*
     * When we allow only specific columns to be eager loaded for a relation,
     * we want API client to be able to request relation without any explicit set of columns
     * and reformat the request on our end.
     */
    protected function prepareInclude()
    {
        $allowedEagerLoads = $this->getSchema()->getAllowedEagerLoads();

        if ($allowedEagerLoads == 'all') {
            return $this->include;
        }

        $formattedInclude = $this->getSchema()->getParser()->parseIncludeValues($this->include);

        $formattedInclude = array_map(
            function ($include) use ($allowedEagerLoads) {
                $allowedInclude = null;

                foreach ($allowedEagerLoads as $allowedEagerLoad) {
                    if ($allowedEagerLoad['relation'] == $include['relation']) {
                        $allowedInclude = $allowedEagerLoad;

                        break;
                    }
                }

                if (empty($include['columns']) && ! is_null($allowedInclude) && ! empty($allowedInclude['columns'])) {
                    $include['columns'] = $allowedInclude['columns'];
                }

                return $include;
            },
            $formattedInclude
        );

        // Format data back to what is expected by server
        return implode(
            $this->getSchema()->getParser()::ARRAY_VALUE_SEPARATOR,
            array_map(
                fn ($include) => empty($include['columns'])
                    ? $include['relation']
                    : $include['relation'] . ':' . implode(':', $include['columns']),
                $formattedInclude
            )
        );
    }
}
