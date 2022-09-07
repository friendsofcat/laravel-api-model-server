<?php

namespace FriendsOfCat\LaravelApiModelServer\Rules;

use Illuminate\Database\Eloquent\Model;
use FriendsOfCat\LaravelApiModelServer\ApiDataParser;
use FriendsOfCat\LaravelApiModelServer\ApiModelSchema;
use FriendsOfCat\LaravelApiModelServer\Concerns\WorksWithRestrictedValues;

abstract class BaseSchemaRule
{
    use WorksWithRestrictedValues;

    public Model $model;
    public ApiDataParser $parser;

    public function __construct(public ApiModelSchema $schema)
    {
        $this->model = $schema->getModel();
        $this->parser = $schema->getParser();
    }

    public function getClientAliases(): array
    {
        $aliases = [];

        if (isset($this->data) && isset($this->data['fields'])) {
            $aliases = array_map(
                fn ($value) => $value['alias'],
                $this->parser->parseFieldsValues($this->data['fields'])
            );
        }

        return $aliases;
    }

    public function isValidTable(string $value): bool
    {
        if ($this->schema->getAllowedEagerLoads() == 'all') {
            return true;
        }

        if (empty($this->schema->getAllowedEagerLoads())) {
            return false;
        }

        $parts = explode('.', $value);
        $table = count($parts) > 1
            ? $parts[0]
            : null;

        if (is_null($table)) {
            return true;
        }

        $allowedEagerLoadsWithTable = $this->schema->getAllowedEagerLoadsWithTable();
        $allowedTables = array_map(
            fn ($relation) => $relation['table'],
            $allowedEagerLoadsWithTable
        );

        $this->errorValue = $value;

        return in_array($table, [$this->model->getTable(), ...$allowedTables])
            && $this->isValidColumn($table, $parts[1], $allowedEagerLoadsWithTable);
    }

    protected function isValidColumn(string $table, string $column, array $allowedData): bool
    {
        if ($column == '*' || $table == $this->model->getTable()) {
            return true;
        }

        $relation = [];

        foreach ($allowedData as $data) {
            if ($data['table'] == $table) {
                $relation = $data;

                break;
            }
        }

        return ! empty($relation) && (empty($relation['columns']) || in_array($column, $relation['columns']));
    }
}
