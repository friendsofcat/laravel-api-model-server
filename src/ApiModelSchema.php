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

    public const ARRAY_KEY_SEPARATOR = ':';

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

    public function parseSortValues(string $values): array
    {
        return array_map(
            function ($value) {
                $trimmedValue = ltrim($value, '-');
                $direction = str_starts_with($value, '-')
                    ? 'desc'
                    : 'asc';

                return [
                    'value' => $trimmedValue,
                    'direction' => $direction
                ];
            },
            $this->parseValues($values)
        );
    }

    public function parseQueryTypeValues(string $values): array
    {
        $values = $this->parseValues($values);

        return [
            'method' => $values[0],
            'args' => array_slice($values, 1)
        ];
    }
}
