<?php

namespace FriendsOfCat\LaravelApiModelServer\Concerns;

trait WorksWithRestrictedValues
{
    public mixed $errorValue;

    public function shouldAllowEverything(string|array $allowedValues): bool
    {
        return $allowedValues === 'all';
    }

    public function shouldAllow(string $value, string|array $allowedValues): bool
    {
        return $this->shouldAllowEverything($allowedValues) || $this->isAllowed($value, $allowedValues);
    }

    public function isAllowed(string $value, array $allowedValues): bool
    {
        $isAllowed = ! $this->isRestricted($value, $allowedValues) && ! $this->isNotAllowed($value, $allowedValues);

        if (! $isAllowed) {
            $this->errorValue = $value;
        }

        return $isAllowed;
    }

    private function isRestricted(string $value, array $allowedValues): bool
    {
        return $allowedValues['mode'] === 'restricted' && in_array($value, $allowedValues['values']);
    }

    private function isNotAllowed(string $value, array $allowedValues): bool
    {
        return $allowedValues['mode'] === 'allowed' && ! in_array($value, $allowedValues['values']);
    }

    public function isEverythingAllowed(array $values, array $allowedValues): bool
    {
        foreach ($values as $value) {
            if (! $this->isAllowed($value, $allowedValues)) {
                return false;
            }
        }

        return true;
    }
}
