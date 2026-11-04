<?php

namespace Javaabu\Imports\Tests\TestSupport\Policies;

class DenyAllPolicy
{
    public function before(): bool
    {
        return false;
    }
}
