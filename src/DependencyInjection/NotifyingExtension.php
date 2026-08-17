<?php

declare(strict_types=1);

namespace App\Notifying\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class NotifyingExtension extends Extension
{
    /**
     * @param array<int, array<string, mixed>> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        unset($configs);

        $configDirectory = __DIR__.'/../../config/component';
        if (!is_file($configDirectory.'/services.yaml')) {
            return;
        }

        $loader = new YamlFileLoader($container, new FileLocator($configDirectory));
        $loader->load('services.yaml');
    }
}
