<?php

namespace MattaDavi\LaravelApiModelServer;

use RuntimeException;
use Illuminate\Database\Eloquent\Model;
use MattaDavi\LaravelApiModelServer\Concerns\HasAllowedRestrictedPairs;

abstract class ApiModelSchema
{
    use HasAllowedRestrictedPairs;

    protected string $model;

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
    protected array $allowedRawClauses = [];

    public const ARRAY_VALUE_SEPARATOR = ',';

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

    public function getAllowedEagerLoads(): string|array
    {
        if ($this->allowedEagerLoads === 'all') {
            return $this->allowedEagerLoads;
        }

        $formattedEagerLoads = [];

        foreach ($this->allowedEagerLoads as $eagerLoad) {
            $formattedEagerLoads = array_merge($formattedEagerLoads, $this->parseIncludeValues($eagerLoad));
        }

        return $formattedEagerLoads;
    }

    /*
     * Resolve Model object from provided class name.
     */
    public function getModel()
    {
        if (class_exists($this->model)) {
            throw new RuntimeException('Defined model for this schema does not exist!');
        }

        $model = app($this->model);

        if (! $model instanceof Model) {
            throw new RuntimeException('Model must be instance of Illuminate\Database\Eloquent\Model');
        }

        return $model;
    }

    public function parseValues(string $values): string|array
    {
        return explode(self::ARRAY_VALUE_SEPARATOR, $values);
    }

    public function parseGroupByValues(string $values): string|array
    {
        return array_map(
            function ($value) {
                $parsedValue = $this->parseFieldValue($value);

                return $parsedValue['alias'] ?? $parsedValue['value'];
            },
            $this->parseValues($values)
        );
    }

    public function parseSelectRawValues(string $values): string|array
    {
        return $this->parseValues($values);
    }

    public function parseSortValues(string $values): array
    {
        return array_map(
            function ($value) {
                $trimmedValue = ltrim($value, '-');
                $direction = str_starts_with($value, '-')
                    ? 'desc'
                    : 'asc';

                return [
                    'value' => $this->resolveFieldValue($trimmedValue),
                    'direction' => $direction,
                ];
            },
            $this->parseValues($values)
        );
    }

    /*
     * Parse relations for eager loading.
     */
    public function parseIncludeValues(string $values): array
    {
        $formattedEagerLoad = explode(':', $values);
        $columns = [];

        if (count($formattedEagerLoad) > 1) {
            $columns = explode(',', $formattedEagerLoad[1]);
        }

        return [$formattedEagerLoad[0] => $columns];
    }

    public function parseNestedValues(string $values): array
    {
        return array_map(
            fn ($nesting) => [
                'key' => $nesting,
                'parts' => $this->parseNestedParts($nesting),
            ],
            $this->parseValues($values)
        );
    }

    /*
     * Resolve ordered nested logic used to properly construct desired query.
     *
     *
     * Example client request => 'and,0:and:e,0:or,1:and,4:and,4:or'
     * --------------
     * [0:and:e] => [parent:boolean:method]
     * 0    => index of parent nesting (determined from order in request)
     * and  => logic of nesting: where(...) / orWhere(...)
     * e    => nullable method of nesting. Configurable via NESTED_METHODS constant.
     *         Default settings:
     *         e: whereExists(...) / orWhereExists(...),
     *         ne: whereNotExists(...) / orWhereNotExists(...),
     */
    public function parseNestedParts(string $values): array
    {
        $parts = explode(':', $values);
        $numOfParts = count($parts);

        $formattedParts = [
            'parent' => null,
            'boolean' => null,
            'method' => null,
        ];

        if ($numOfParts == 1) {
            $formattedParts['boolean'] = $parts[0];
        } elseif ($numOfParts == 2) {
            if (is_numeric($parts[0])) {
                $formattedParts['parent'] = $parts[0];
                $formattedParts['boolean'] = $parts[1];
            } else {
                $formattedParts['boolean'] = $parts[0];
                $formattedParts['method'] = $parts[1];
            }
        } else {
            $formattedParts = [
                'parent' => $parts[0],
                'boolean' => $parts[1],
                'method' => $parts[2],
            ];
        }

        return $formattedParts;
    }

    /*
     * Resolve desired method to execute query.
     * You can set possible values via $allowedMethods.
     * Defaults to 'get()'.
     *
     *
     * 1. Example request from client => 'count'
     * --------------
     * Method to execute => count()
     *
     *
     * 2. Example request from client => 'avg:price'
     * --------------
     * Method to execute => avg('price')
     */
    public function parseQueryTypeValues(string $values): array
    {
        $values = $this->parseValues($values);

        return [
            'method' => $values[0],
            'args' => array_slice($values, 1),
        ];
    }

    public function parseFieldsValues(string $values): array
    {
        return array_map(
            fn ($value) => $this->parseFieldValue($value),
            $this->parseValues($values)
        );
    }

    public function parseFieldValue(string $value): array
    {
        $formattedField = explode(' as ', $value);

        return [
            'value' => $this->resolveFieldValue($formattedField[0]),
            'alias' => $this->resolveFieldAliasValue($formattedField),
        ];
    }

    /*
     * We need to take server defined aliases into consideration
     * while resolving allowed attributes.
     */
    public function resolveFieldValue(string $value): string
    {
        return $this->attributeAliases[$value] ?? $value;
    }

    /*
     * We need to respect client defined alias while resolving allowed attribute,
     * even when it is resolved via server defined alias.
     *
     *
     * 1. Example request from client: 'id as account'
     * --------------
     * Server settings: $attributeAliases = ['id' => 'iban']
     * Value to use in query => 'iban as account'
     *
     *
     * 2. Example request from client: 'id'
     * --------------
     * Server settings: $attributeAliases = ['id' => 'iban']
     * Value to use in query => 'iban as id'
     */
    public function resolveFieldAliasValue(array $field): string
    {
        $alias = null;

        if (isset($this->attributeAliases[$field[0]])) {
            $alias = $field[0];
        }

        return $field[1] ?? $alias;
    }
}
