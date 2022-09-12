<?php

namespace FriendsOfCat\LaravelApiModel;

use Illuminate\Routing\Router;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;
use FriendsOfCat\LaravelApiModelServer\Routing\ApiModelRouter;
use Illuminate\Support\ServiceProvider as ServiceProviderBase;
use FriendsOfCat\LaravelApiModelServer\Routing\ApiModelResourceRegistrar;

class ServiceProvider extends ServiceProviderBase
{
    public function register()
    {
        $this->app->singleton(
            ApiModelRouter::class,
            function ($app) {
                return new ApiModelRouter($app->make(Dispatcher::class), $app->make(Container::class));
            }
        );

        $this->app->singleton(
            ApiModelResourceRegistrar::class,
            function ($app) {
                return new ApiModelResourceRegistrar($app->make(Router::class));
            }
        );
    }
}
