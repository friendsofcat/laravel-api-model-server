<?php

namespace FriendsOfCat\LaravelApiModelServer;

use Illuminate\Database\Eloquent\Builder;

class ApiQueryBuilder
{
    public array $method = [
        'method' => 'get',
        'args' => [],
    ];

    public function __construct(public Builder $query)
    {
    }

    /*
     * Forward methods to query builder
     * to provide more convenient way of chaining methods.
     */
    public function __call($method, $parameters)
    {
        if (! method_exists($this, $method)) {
            return $this->query->$method(...$parameters);
        }

        return $this->$method(...$parameters);
    }

    public function prepare(array $data): self
    {
        $this->prepareMethod($data);

        return $this
            ->buildWith($data)
            ->buildSelect($data)
            ->buildWhere($data)
            ->buildOrderBy($data)
            ->buildLimitOffset($data)
            ->buildGroupBy($data);
    }

    public function execute(bool $withoutAppends = true)
    {
        $result = $this->query->{$this->method['method']}(...$this->method['args']);

        if ($withoutAppends && $this->isGet()) {
            $result->every->setAppends([]);
        } elseif ($this->isAggregate()) {
            $result = [
                ['aggregate' => $result],
            ];
        } elseif ($this->isExists()) {
            $result = [
                ['exists' => $result],
            ];
        }

        return $result;
    }

    protected function buildWith(array $data): self
    {
        if (isset($data['includes'])) {
            $this->query->with($data['includes']);
        }

        return $this;
    }

    protected function buildSelect(array $data): self
    {
        if (! isset($data['fields'])) {
            return $this;
        }

        $formattedFields = array_map(
            fn ($field) => $this->constructColumnForSelect($field['value'], $field['alias']),
            $data['fields']
        );

        $this->query->select($formattedFields);

        return $this;
    }

    // todo: handle nested logic. Current workaround is to use externalScopes
    protected function buildWhere(array $data): self
    {
        if (! isset($data['filter'])) {
            return $this;
        }

        foreach ($data['filter'] as $where) {
            $q = $this->query;

            if ($where['nested'] >= 0) {
            }

            match ($where['type']) {
                'Scope' => $q->{$where['scope']}(...$where['args']),
                'Basic' => $q->where($where['column'], $where['operator'], $where['value'], $where['boolean']),
                'In' => $q->whereIn($where['column'], $where['values'], $where['boolean']),
                'NotIn' => $q->whereNotIn($where['column'], $where['values'], $where['boolean']),
                'InRaw' => $q->whereIntegerInRaw($where['column'], $where['value'], $where['boolean']),
                'NotInRaw' => $q->whereIntegerNotInRaw($where['column'], $where['value']),
                'Null' => $q->whereNull($where['column'], $where['boolean']),
                'NotNull' => $q->whereNotNull($where['column'], $where['boolean']),
                'between' => $q->whereNotBetween($where['column'], $where['values'], $where['boolean']),
                'Date' => $q->whereDate($where['column'], $where['operator'], $where['value'], $where['boolean']),
                'Time' => $q->whereTime($where['column'], $where['operator'], $where['value'], $where['boolean']),
                'Year' => $q->whereYear($where['column'], $where['operator'], $where['value'], $where['boolean']),
                'Day' => $q->whereDay($where['column'], $where['operator'], $where['value'], $where['boolean']),
                'raw' => $q->whereRaw($where['sql'], [], $where['boolean']),
                'Column' => $q->whereColumn($where['first'], $where['operator'], $where['second'], $where['boolean']),
                default => null
            };

            unset($where['nested']);
        }

        return $this;
    }

    protected function buildOrderBy(array $data): self
    {
        if (! isset($data['sort'])) {
            return $this;
        }

        foreach ($data['sort'] as $sort) {
            $this->query->orderBy($sort['value'], $sort['direction']);
        }

        return $this;
    }

    protected function buildLimitOffset(array $data): self
    {
        if (isset($data['limit'])) {
            $this->query->limit($data['limit']);
        }

        if (isset($data['offset'])) {
            $this->query->offset($data['offset']);
        }

        return $this;
    }

    protected function buildGroupBy(array $data): self
    {
        if (isset($data['groupBy'])) {
            $this->query->groupBy($data['groupBy']);
        }

        return $this;
    }

    protected function prepareMethod(array $data): void
    {
        if (isset($data['queryType'])) {
            $this->method = $data['queryType'];
        }
    }

    protected function constructColumnForSelect(string $value, ?string $alias): string
    {
        $alias = is_null($alias)
            ? ''
            : 'as ' . $alias;

        return $value . $alias;
    }

    public function isGet(): bool
    {
        return $this->method['method'] == 'get';
    }

    public function isExists(): bool
    {
        return $this->method['method'] == 'exists';
    }

    public function isAggregate(): bool
    {
        return ! in_array($this->method['method'], ['get', 'exists', 'delete']);
    }
}
