<?php

namespace MattaDavi\LaravelApiModelServer;

class ApiDataParser
{
    public function __construct(public array $attributeAliases = [], public array $scopeAliases = [])
    {
    }

    /*
     * Operators with url safe alias
     */
    public const OPERATORS_WITH_ALIAS = [
        '=' => 'e',
        '<' => 'lt',
        '>' => 'gt',
        '<=' => 'lte',
        '>=' => 'gte',
        '<>' => 'ne',
        '!=' => 'ne',
        '|' => 'bo',
        '^' => 'beo',
        '<<' => 'ls',
        '>>' => 'rs',
        '&' => 'ba',
        '&~' => 'bai',
        '~' => 'bi',
        '~*' => 'bim',
        '!~' => 'nbi',
        '!~*' => 'nbim',
        '~~*' => 'bibim',
        '!~~*' => 'nbibim',
    ];

    public const NON_BASIC_OPERATORS = [
        'in',
        'in raw',
        'not in',
        'not in raw',
        'is null',
        'is not null',
        'not between',
        'fulltext',
        'date',
        'day',
        'year',
        'time',
        'scope',
    ];

    public const ARRAY_VALUE_SEPARATOR = ',';

    public function formatData(array $data): array
    {
        $formattedData = [];

        foreach ($data as $key => $value) {
            $formattedData[$key] = match ($key) {
                'filter' => $this->parseFilterValues($value),
                'sort' => $this->parseSortValues($value),
                'nested' => $this->parseNestedValues($value),
                'queryType' => $this->parseQueryTypeValues($value),
                'fields' => $this->parseFieldsValues($value),
                'include' => $this->parseIncludeValues($value),
                'selectRaw' => $this->parseSelectRawValues($value),
                'groupBy' => $this->parseGroupByValues($value),
                'page', 'per_page', 'limit', 'offset' => $value,
            };
        }

        return $formattedData;
    }

    protected function getOperator(string $operator): string
    {
        return str_replace('_', ' ', array_search($operator, self::OPERATORS_WITH_ALIAS) ?: $operator);
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
    public function resolveFieldAliasValue(array $field): ?string
    {
        $alias = null;

        if (isset($this->attributeAliases[$field[0]])) {
            $alias = $field[0];
        }

        return $field[1] ?? $alias;
    }

    public function parseFilterValues(array $values): array
    {
        $formattedFilters = [];

        foreach ($values as $key => $value) {
            $formattedFilters[] = $this->parseFilterValue($key, $value);
        }

        return $formattedFilters;
    }

    public function parseFilterValue(string $key, string $values): array
    {
        $formattedValues = $this->parseValues($values);
        $parsedKey = explode(':', $key);

        return match (true) {
            $this->isScope($parsedKey) => $this->parseScope($parsedKey, $formattedValues),
            $this->isWhereBasic($parsedKey) => $this->parseWhereBasic($parsedKey, $formattedValues),
            $this->isWhereIn($parsedKey) => $this->parseWhereIn($parsedKey, $formattedValues),
            $this->isWhereNotIn($parsedKey) => $this->parseWhereNotIn($parsedKey, $formattedValues),
            $this->isWhereInRaw($parsedKey) => $this->parseWhereInRaw($parsedKey, $formattedValues),
            $this->isWhereNotInRaw($parsedKey) => $this->parseWhereNotInRaw($parsedKey, $formattedValues),
            $this->isWhereNotBetween($parsedKey) => $this->parseWhereNotBetween($parsedKey, $formattedValues),
            $this->isWhereNull($parsedKey) => $this->parseWhereNull($parsedKey, $formattedValues),
            $this->isWhereNotNull($parsedKey) => $this->parseWhereNotNull($parsedKey, $formattedValues),
            $this->isWhereDate($parsedKey) => $this->parseWhereDate($parsedKey, $formattedValues),
            $this->isWhereDay($parsedKey) => $this->parseWhereDay($parsedKey, $formattedValues),
            $this->isWhereYear($parsedKey) => $this->parseWhereYear($parsedKey, $formattedValues),
            $this->isWhereTime($parsedKey) => $this->parseWhereTime($parsedKey, $formattedValues),
            $this->isWhereColumn($parsedKey) => $this->parseWhereColumn($parsedKey, $formattedValues),
            $this->isWhereRaw($parsedKey) => $this->parseWhereRaw($parsedKey, $formattedValues),
            default => []
        };
    }

    public function parseScope(array $setting, array $args)
    {
        return [
            'type' => 'Scope',
            'nested' => is_numeric($setting[0]) ? $setting[0] : -1,
            'scope' => $setting[count($setting) - 2],
            'args' => $args,
        ];
    }

    public function parseWhereNull(array $setting, array $args)
    {
        $numOfArgs = count($setting);

        return [
            'type' => 'Null',
            'nested' => is_numeric($setting[0]) ? $setting[0] : -1,
            'column' => $setting[$numOfArgs - 2],
            'boolean' => $numOfArgs > 2 ? $setting[$numOfArgs - 3] : 'and',
        ];
    }

    public function parseWhereNotNull(array $setting, array $args)
    {
        $numOfArgs = count($setting);

        return [
            'type' => 'NotNull',
            'nested' => is_numeric($setting[0]) ? $setting[0] : -1,
            'column' => $setting[$numOfArgs - 2],
            'boolean' => $numOfArgs > 2 ? $setting[$numOfArgs - 3] : 'and',
        ];
    }

    public function parseWhereBasic(array $setting, array $args)
    {
        $numOfArgs = count($setting);

        return [
            'type' => 'Basic',
            'nested' => is_numeric($setting[0]) ? $setting[0] : -1,
            'column' => $setting[$numOfArgs - 2],
            'operator' => $this->getOperator($setting[$numOfArgs - 1]),
            'value' => $args[0],
            'boolean' => $numOfArgs > 2 ? $setting[$numOfArgs - 3] : 'and',
        ];
    }

    public function parseWhereIn(array $setting, array $args)
    {
        $numOfArgs = count($setting);

        return [
            'type' => 'In',
            'nested' => is_numeric($setting[0]) ? $setting[0] : -1,
            'column' => $setting[$numOfArgs - 2],
            'values' => $args,
            'boolean' => $numOfArgs > 2 ? $setting[$numOfArgs - 3] : 'and',
        ];
    }

    public function parseWhereNotIn(array $setting, array $args)
    {
        $numOfArgs = count($setting);

        return [
            'type' => 'NotIn',
            'nested' => is_numeric($setting[0]) ? $setting[0] : -1,
            'column' => $setting[$numOfArgs - 2],
            'values' => $args,
            'boolean' => $numOfArgs > 2 ? $setting[$numOfArgs - 3] : 'and',
        ];
    }

    public function parseWhereInRaw(array $setting, array $args)
    {
        $numOfArgs = count($setting);

        return [
            'type' => 'InRaw',
            'nested' => is_numeric($setting[0]) ? $setting[0] : -1,
            'column' => $setting[$numOfArgs - 2],
            'value' => $args,
            'boolean' => $numOfArgs > 2 ? $setting[$numOfArgs - 3] : 'and',
        ];
    }

    public function parseWhereNotInRaw(array $setting, array $args)
    {
        $numOfArgs = count($setting);

        return [
            'type' => 'NotInRaw',
            'nested' => is_numeric($setting[0]) ? $setting[0] : -1,
            'column' => $setting[$numOfArgs - 2],
            'value' => $args,
            'boolean' => $numOfArgs > 2 ? $setting[$numOfArgs - 3] : 'and',
        ];
    }

    public function parseWhereNotBetween(array $setting, array $args)
    {
        $numOfArgs = count($setting);

        return [
            'type' => 'between',
            'nested' => is_numeric($setting[0]) ? $setting[0] : -1,
            'column' => $setting[$numOfArgs - 2],
            'values' => $args,
            'boolean' => $numOfArgs > 2 ? $setting[$numOfArgs - 3] : 'and',
            'not' => true,
        ];
    }

    public function parseWhereDate(array $setting, array $args)
    {
        $numOfArgs = count($setting);

        return [
            'type' => 'Date',
            'nested' => is_numeric($setting[0]) ? $setting[0] : -1,
            'column' => $setting[$numOfArgs - 3],
            'operator' => $this->getOperator($setting[$numOfArgs - 2]),
            'value' => $args[0],
            'boolean' => $numOfArgs > 3 ? $setting[$numOfArgs - 4] : 'and',
        ];
    }

    public function parseWhereTime(array $setting, array $args)
    {
        $numOfArgs = count($setting);

        return [
            'type' => 'Time',
            'nested' => is_numeric($setting[0]) ? $setting[0] : -1,
            'column' => $setting[$numOfArgs - 3],
            'operator' => $this->getOperator($setting[$numOfArgs - 2]),
            'value' => $args[0],
            'boolean' => $numOfArgs > 3 ? $setting[$numOfArgs - 4] : 'and',
        ];
    }

    public function parseWhereYear(array $setting, array $args)
    {
        $numOfArgs = count($setting);

        return [
            'type' => 'Year',
            'nested' => is_numeric($setting[0]) ? $setting[0] : -1,
            'column' => $setting[$numOfArgs - 3],
            'operator' => $this->getOperator($setting[$numOfArgs - 2]),
            'value' => $args[0],
            'boolean' => $numOfArgs > 3 ? $setting[$numOfArgs - 4] : 'and',
        ];
    }

    public function parseWhereDay(array $setting, array $args)
    {
        $numOfArgs = count($setting);

        return [
            'type' => 'Day',
            'nested' => is_numeric($setting[0]) ? $setting[0] : -1,
            'column' => $setting[$numOfArgs - 3],
            'operator' => $this->getOperator($setting[$numOfArgs - 2]),
            'value' => $args[0],
            'boolean' => $numOfArgs > 3 ? $setting[$numOfArgs - 4] : 'and',
        ];
    }

    public function parseWhereRaw(array $setting, array $args)
    {
        $numOfArgs = count($setting);

        return [
            'type' => 'raw',
            'nested' => is_numeric($setting[0]) ? $setting[0] : -1,
            'sql' => $args[0],
            'boolean' => $numOfArgs > 2 ? $setting[$numOfArgs - 3] : 'and',
        ];
    }

    public function parseWhereColumn(array $setting, array $args)
    {
        return [
            'type' => 'Column',
            'nested' => is_numeric($setting[0]) ? $setting[0] : -1,
            'first' => $args[0],
            'operator' => $this->getOperator($args[1]),
            'second' => $args[2],
        ];
    }

    public function isWhereTypeFromOperator(array $values, string $type): bool
    {
        return $this->getOperator($values[count($values) - 1]) == $type;
    }

    public function isWhereBasic(array $values): bool
    {
        return ! in_array($this->getOperator($values[count($values) - 1]), self::NON_BASIC_OPERATORS)
            && count(explode('-', $values[count($values) - 1])) == 1;
    }

    public function isScope(array $values): bool
    {
        return $this->isWhereTypeFromOperator($values, 'scope');
    }

    public function isWhereIn(array $values): bool
    {
        return $this->isWhereTypeFromOperator($values, 'in');
    }

    public function isWhereInRaw(array $values): bool
    {
        return $this->isWhereTypeFromOperator($values, 'in raw');
    }

    public function isWhereNotIn(array $values): bool
    {
        return $this->isWhereTypeFromOperator($values, 'not in');
    }

    public function isWhereNotInRaw(array $values): bool
    {
        return $this->isWhereTypeFromOperator($values, 'not in raw');
    }

    public function isWhereNotBetween(array $values): bool
    {
        return $this->isWhereTypeFromOperator($values, 'not between');
    }

    public function isWhereNull(array $values): bool
    {
        return $this->isWhereTypeFromOperator($values, 'is null');
    }

    public function isWhereNotNull(array $values): bool
    {
        return $this->isWhereTypeFromOperator($values, 'is not null');
    }

    public function isWhereFullText(array $values): bool
    {
        return $this->isWhereTypeFromOperator($values, 'fulltext');
    }

    public function isWhereDate(array $values): bool
    {
        return $this->isWhereTypeFromOperator($values, 'date');
    }

    public function isWhereDay(array $values): bool
    {
        return $this->isWhereTypeFromOperator($values, 'day');
    }

    public function isWhereYear(array $values): bool
    {
        return $this->isWhereTypeFromOperator($values, 'year');
    }

    public function isWhereTime(array $values): bool
    {
        return $this->isWhereTypeFromOperator($values, 'time');
    }

    public function isWhereColumn(array $values): bool
    {
        $numberOfParts = count($values);
        $operatorParts = explode('-', $values[$numberOfParts - 1]);

        return in_array($numberOfParts, [2, 3]) && isset($operatorParts[1]) && $operatorParts[1] == 'column';
    }

    public function isWhereRaw(array $values): bool
    {
        $numberOfParts = count($values);
        $operatorParts = explode('-', $values[$numberOfParts - 1]);

        return in_array($numberOfParts, [2, 3]) && isset($operatorParts[1]) && $operatorParts[1] == 'raw';
    }
}
