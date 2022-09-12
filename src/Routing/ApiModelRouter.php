<?php

namespace FriendsOfCat\LaravelApiModelServer\Routing;

use Illuminate\Routing\Router;
use Illuminate\Routing\PendingResourceRegistration;

class ApiModelRouter extends Router
{
    public function resource($name, $controller, array $options = [])
    {
        $only = ['index', 'store', 'update', 'destroy'];

        if (isset($options['except'])) {
            $only = array_diff($only, (array) $options['except']);
        }

        if ($this->container && $this->container->bound(ApiModelResourceRegistrar::class)) {
            $registrar = $this->container->make(ApiModelResourceRegistrar::class);
        } else {
            $registrar = new ApiModelResourceRegistrar($this);
        }

        return new PendingResourceRegistration(
            $registrar,
            $name,
            $controller,
            array_merge(['only' => $only], $options)
        );
    }

    /**
     * Prefix the given URI with the last prefix.
     *
     * @param  string  $uri
     * @return string
     */
    protected function prefix($uri)
    {
        return trim(trim(app('router')->getLastGroupPrefix(), '/') . '/' . trim($uri, '/'), '/') ?: '/';
    }

    /*
     * Create route with custom logic
     * and add it to primary route collection.
     */
    public function addRoute($methods, $uri, $action)
    {
        $baseRouter = app('router');
        $route = $this->createRoute($methods, $uri, $action);

        return $baseRouter->routes->add($route);
    }

    /**
     * Determine if the router currently has a group stack.
     *
     * @return bool
     */
    public function hasGroupStack()
    {
        return ! empty(app('router')->groupStack);
    }

    /**
     * Merge the group stack with the controller action.
     *
     * @param  \Illuminate\Routing\Route  $route
     * @return void
     */
    protected function mergeGroupAttributesIntoRoute($route)
    {
        $route->setAction(app('router')->mergeWithLastGroup(
            $route->getAction(),
            $prependExistingPrefix = false
        ));
    }
}
