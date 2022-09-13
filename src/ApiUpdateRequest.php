<?php

namespace FriendsOfCat\LaravelApiModelServer;

abstract class ApiUpdateRequest extends ApiRequest
{
    public function rules(): array
    {
        return collect(parent::rules())->mapWithKeys(
            fn ($item, $key) => ['params.' . $key => $item]
        )
            ->merge(collect($this->dataRules())->mapWithKeys(
                fn ($item, $key) => ['data.' . $key => $item]
            ))
            ->toArray();
    }

    public function dataRules(): array
    {
        return [];
    }
}
