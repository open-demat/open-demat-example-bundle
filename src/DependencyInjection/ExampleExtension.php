<?php

namespace OpenDemat\ExampleBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

class ExampleExtension extends ConfigurableExtension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container): void
    {
        if ($container->hasExtension('doctrine_migrations')) {
            $container->prependExtensionConfig('doctrine_migrations', [
                'migrations_paths' => [
                    'OpenDemat\\ExampleBundle\\Migrations' =>
                        '%kernel.project_dir%/app_open_demat/example-bundle/migrations',
                ],
            ]);
        }

        if ($container->hasExtension('doctrine')) {
            $container->prependExtensionConfig('doctrine', [
                'orm' => [
                    'mappings' => [
                        'OpenDematExampleBundle' => [
                            'is_bundle' => false,
                            'type' => 'attribute',
                            'dir' => '%kernel.project_dir%/app_open_demat/example-bundle/src/Entity',
                            'prefix' => 'OpenDemat\\ExampleBundle\\Entity',
                            'alias' => 'OpenDematExampleBundle',
                        ],
                    ],
                ],
            ]);
        }

        if ($container->hasExtension('security')) {
            $container->prependExtensionConfig('security', [
                'role_hierarchy' => [
                    'ROLE_EXAMPLE_GESTIONNAIRE' => ['ROLE_USER'],
                    'ROLE_EXAMPLE_POWERUSER' => ['ROLE_EXAMPLE_GESTIONNAIRE'],
                ],
            ]);
        }

        if ($container->hasExtension('framework')) {
            $container->prependExtensionConfig('framework', [
                'workflows' => [
                    'example_demande_achat' => [
                        'type' => 'state_machine',
                        'marking_store' => [
                            'type' => 'method',
                            'property' => 'statutTraitement',
                        ],
                        'supports' => [
                            'OpenDemat\\ExampleBundle\\Entity\\DemandeAchatInterne',
                        ],
                        'initial_marking' => 'brouillon',
                        'places' => [
                            'brouillon',
                            'soumise',
                            'a_corriger',
                            'validee',
                            'refusee',
                            'annulee',
                            'terminee',
                        ],
                        'transitions' => [
                            'soumettre' => [
                                'from' => ['brouillon', 'a_corriger'],
                                'to' => 'soumise',
                            ],
                            'demander_correction' => [
                                'from' => 'soumise',
                                'to' => 'a_corriger',
                            ],
                            'valider' => [
                                'from' => 'soumise',
                                'to' => 'validee',
                            ],
                            'refuser_definitivement' => [
                                'from' => 'soumise',
                                'to' => 'refusee',
                            ],
                            'annuler' => [
                                'from' => ['brouillon', 'soumise', 'a_corriger'],
                                'to' => 'annulee',
                            ],
                            'terminer' => [
                                'from' => 'validee',
                                'to' => 'terminee',
                            ],
                        ],
                    ],
                ],
            ]);
        }
    }

    protected function loadInternal(array $mergedConfig, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../../Resources/config')
        );

        $loader->load('services.yaml');
    }
}
