<?php

namespace Ttpryg\ContentEngine\Contracts;

interface EventDispatcherInterface
{
    public function dispatch(object $event): void;
}
