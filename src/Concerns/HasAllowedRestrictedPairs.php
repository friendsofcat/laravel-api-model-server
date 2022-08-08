<?php

namespace MattaDavi\LaravelApiModelServer\Concerns;

use BadMethodCallException;

trait HasAllowedRestrictedPairs
{
    public function __call($method, $parameters)
    {
        /*
         * Provide ability to resolve allowed/restricted property pairs in convenient way.
         *
         * Example:
         * $this->getAllowedAttributes()
         */
        if (! method_exists($this, $method) && \Str::startsWith($method, 'getAllowed')) {
            $propName = substr($method, strlen('getAllowed'));

            if (strlen($propName)) {
                return isset($this->{"allowed${propName}"}) && isset($this->{"restricted${propName}"})
                    ? $this->getAllowed($propName)
                    : throw new BadMethodCallException("Method [${method}] does not exist.");
            }
        }

        return $this->$method(...$parameters);
    }

    /*
     * Resolve logic of allowed/restricted property pair and returns key for validation.
     * Helpers found in WorksWithRestrictedValues trait.
     */
    protected function getAllowed($propName): string|array
    {
        $formattedPropName = \Str::ucfirst($propName);

        [$allowed, $restricted] = [
            $this->{"allowed${formattedPropName}"},
            $this->{"restricted${formattedPropName}"},
        ];

        return match (true) {
            ($allowed === 'all' || empty($allowed)) && ! empty($restricted) => [
                'values' => $restricted,
                'mode' => 'restricted',
            ],
            $allowed !== 'all' => [
                'values' => $allowed,
                'mode' => 'allowed',
            ],
            default => $allowed
        };
    }
}
