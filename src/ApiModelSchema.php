<?php

namespace MattaDavi\LaravelApiModelServer;

use RuntimeException;
use Illuminate\Database\Eloquent\Model;
use MattaDavi\LaravelApiModelServer\Concerns\HasAllowedRestrictedPairs;

abstract class ApiModelSchema
{
    use HasAllowedRestrictedPairs;

    protected string $model;

    protected string $parser = ApiDataParser::class;

    protected string $builder = ApiQueryBuilder::class;

    /*
     * Define what attributes would be queryable via API.
     *
     * If left empty or set to 'all' and there are some $restrictedAttributes specified,
     * allowed attributes become 'all but $restrictedAttributes'.
     *
     * Possible values:
     * string[] => only specified attributes
     * 'all'    => everything is queryable
     */
    protected array|string $allowedAttributes = [];

    /*
     * Define what attributes cannot be queryable via API.
     *
     * If specified and $allowedAttributes are empty or 'all',
     * allowed attributes become 'all but $restrictedAttributes'.
     */
    protected array $restrictedAttributes = [];

    /*
     * Some attributes might not have API friendly name,
     * you do not wish to expose real name
     * or just need to use different name for sake of compatibility with API client.
     *
     * You can define those aliases as a key value pair.
     *
     * NOTE:
     * Make sure there is no attribute with exactly same name as defined alias.
     *
     * Example value: ['id' => 'IBAN']
     * -------
     * 'id'     => key used by API client
     * 'IBAN'   => real attribute used by your model
     */
    protected array $attributeAliases = [];

    /*
     * Define what scopes would be queryable via API.
     *
     * If left empty or set to 'all' and there are some $restrictedScopes specified,
     * allowed attributes become 'all but $restrictedScopes'.
     *
     * Possible values:
     * string[] => only specified scopes
     * 'all'    => everything is queryable
     */
    protected array|string $allowedScopes = [];

    /*
     * Define what scopes cannot be queryable via API.
     *
     * If specified and $allowedScopes are empty or 'all',
     * allowed attributes become 'all but $restrictedScopes'.
     */
    protected array $restrictedScopes = [];

    /*
     * Some scopes might not have API friendly name,
     * you can define those aliases as a key value pair.
     *
     * NOTE:
     * Make sure there is no scope with exactly same name as defined alias.
     *
     * Example value: ['isActive' => 'activeInLastTwoYears']
     * -------
     * 'isActive'               => key used by API client
     * 'activeInLastTwoYears'   => real scope used by your model
     */
    protected array $scopeAliases = [];

    /*
     * Define eager loads callable via API.
     *
     * Possible values:
     * string[] => only specified relations.
     *             Support nested relations and select constrains.
     *             Example: 'user', 'user.car', 'user:id,created_at'
     * 'all'    => every method
     */
    protected array|string $allowedEagerLoads = [];

    /*
     * Define what methods can be called via API.
     *
     * Possible values:
     * string[] => only specified methods (exists, count, avg...)
     * 'all'    => every method
     */
    protected array|string $allowedMethods = [
        'get',
        'exists',
    ];

    /*
     * Define what methods cannot be called via API.
     *
     * If specified and $allowedMethods are empty or 'all',
     * allowed attributes become 'all but $restrictedMethods'.
     */
    protected array $restrictedMethods = [];

    /*
     * Define what raw clauses are usable via API.
     * Proceed with caution!
     *
     * Possible values:
     * string[] => only specified methods (select, where, groupBy...)
     * 'all'    => every method
     */
    protected array $allowedRawClauses = [
        'whereIntegerInRaw'
    ];

    public const NESTED_METHODS = [
        'e' => 'exists',
        'ne' => 'notExists',
    ];

    public function getAllowedRawClauses(): string|array
    {
        return $this->allowedRawClauses;
    }

    public function getAttributeAliases(): string|array
    {
        return $this->attributeAliases;
    }

    public function getScopeAliases(): string|array
    {
        return $this->attributeAliases;
    }

    public function getAllowedEagerLoads(): string|array
    {
        if ($this->allowedEagerLoads === 'all') {
            return $this->allowedEagerLoads;
        }

        $formattedEagerLoads = [];

        foreach ($this->allowedEagerLoads as $eagerLoad) {
            $formattedEagerLoads = array_merge($formattedEagerLoads, $this->getParser()->parseIncludeValues($eagerLoad));
        }

        return $formattedEagerLoads;
    }

    /*
     * Resolve Model object from provided class name.
     */
    public function getModel()
    {
        if (! class_exists($this->model)) {
            throw new RuntimeException('Defined model for this schema does not exist!');
        }

        $model = app($this->model);

        if (! $model instanceof Model) {
            throw new RuntimeException('Model must be an instance of Illuminate\Database\Eloquent\Model');
        }

        return $model;
    }

    /*
     * Resolve Parser object from provided class name.
     */
    public function getParser()
    {
        if (! class_exists($this->parser)) {
            throw new RuntimeException('Defined parser for this schema does not exist!');
        }

        $parser = app()->makeWith($this->parser, [
            'attributeAliases' => $this->getAttributeAliases(),
            'scopeAliases' => $this->getScopeAliases(),
        ]);

        if (! $parser instanceof ApiDataParser) {
            throw new RuntimeException('Parser must be an instance of MattaDavi\LaravelApiModelServer\ApiDataParser');
        }

        return $parser;
    }

    /*
     * Resolve Builder object from provided class name.
     */
    public function getBuilder()
    {
        if (! class_exists($this->builder)) {
            throw new RuntimeException('Defined Builder for this schema does not exist!');
        }

        $builder = app()->makeWith($this->builder, [
            'query' => $this->getModel()->newModelQuery(),
        ]);

        if (! $builder instanceof ApiQueryBuilder) {
            throw new RuntimeException('Builder must be an instance of MattaDavi\LaravelApiModelServer\ApiQueryBuilder');
        }

        return $builder;
    }

    public function getAllowedEagerLoadsWithTable(): array
    {
        $eagerLoadsWithColumns = $this->getAllowedEagerLoads();

        $relationTables = $this->allowedEagerLoads != 'all'
            ? array_map(
                fn ($relation) => [
                    'table' => $this->resolveRelationTable($relation),
                    'columns' => $eagerLoadsWithColumns[$relation],
                ],
                array_keys($eagerLoadsWithColumns)
            )
            : [];

        return $relationTables;
    }

    protected function resolveRelationTable($relation): string
    {
        $parts = explode('.', $relation);
        $modelData = [];
        $baseModel = $this->getModel();

        foreach ($parts as $relation) {
            if (empty($modelData) && ! method_exists($baseModel, $relation)) {
                return '';
            }

            $related = empty($modelData)
                ? $baseModel->$relation()->getRelated()
                : $modelData['model']->$relation()->getRelated();

            $modelData = [
                'model' => $related,
                'table' => $related->getTable(),
            ];
        }

        return $modelData['table'];
    }

    public function prepare(array $data): ApiQueryBuilder
    {
        $formattedData = $this->getParser()->formatData($data);

        return $this->getBuilder()->prepare($formattedData);
    }
}
