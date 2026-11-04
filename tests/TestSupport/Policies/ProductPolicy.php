<?php

namespace Javaabu\Imports\Tests\TestSupport\Policies;

use Javaabu\Permissions\Policies\ResourcePolicy;

class ProductPolicy extends ResourcePolicy
{
    protected string $resource = 'products';
}
