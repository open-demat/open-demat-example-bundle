<?php

namespace OpenDemat\ExampleBundle\Hub;

use OpenDemat\Core\Hub\AppDefinitionInterface;

class ExampleGlobalAppDefinition implements AppDefinitionInterface
{
    public function getKey(): string
    {
        return 'EXAMPLE';
    }

    public function getTitle(): string
    {
        return 'achats internes';
    }

    public function getRoute(): string
    {
        return 'open_demat_example_demande_achat_index';
    }

    public function getIcon(): string
    {
        return 'example-achats.svg';
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function getDescription(): string
    {
        return 'Circuit exemple de validation et de suivi d une demande d achat interne.';
    }
}
