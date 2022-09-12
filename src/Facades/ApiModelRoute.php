<?php

namespace FriendsOfCat\LaravelApiModelServer\Facades;

use Illuminate\Support\Facades\Facade;
use FriendsOfCat\LaravelApiModelServer\Routing\ApiModelRouter;

class ApiModelRoute extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return ApiModelRouter::class;
    }
}
