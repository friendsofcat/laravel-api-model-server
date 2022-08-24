<?php

namespace MattaDavi\LaravelApiModelServer;

use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Builder;

class ApiQueryBuilder
{
    public $method = [
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
        Log::info('Preparing query');
        $this->prepareMethod($data);
        Log::info('Method prepared');

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
        Log::info('Executing query');
        Log::info($this->method['method']);
        Log::info($this->method['args']);
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
        return ! in_array($this->method['method'], ['get', 'exists']);
    }

    protected function buildWith(array $data): self
    {
        if (isset($data['includes'])) {
            $this->query->with($data['includes']);
        }
        Log::info('With prepared');

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
        Log::info('Select prepared');

        return $this;
    }

    protected function buildWhere(array $data): self
    {
        //todo

        Log::info('Where prepared');

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

        Log::info('Order prepared');

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
        Log::info('Limit prepared');

        return $this;
    }

    protected function buildGroupBy(array $data): self
    {
        if (isset($data['groupBy'])) {
            $this->query->groupBy($data['groupBy']);
        }
        Log::info('Groupby prepared');

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
}
