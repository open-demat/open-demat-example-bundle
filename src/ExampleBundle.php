<?php

namespace OpenDemat\ExampleBundle;

use OpenDemat\ExampleBundle\DependencyInjection\ExampleExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class ExampleBundle extends Bundle
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        return new ExampleExtension();
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
