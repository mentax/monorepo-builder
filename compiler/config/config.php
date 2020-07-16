<?php declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symplify\PackageBuilder\Console\Style\SymfonyStyleFactory;
use Symplify\SmartFileSystem\SmartFileSystem;

return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();

    $parameters->set('dataDir', '%kernel.project_dir%/build');

    $parameters->set('buildDir', '%kernel.project_dir%/..');

    $services = $containerConfigurator->services();

    $services->defaults()
        ->public()
        ->autowire()
        ->autoconfigure();

    $services->load('Symplify\MonorepoBuilder\Compiler\\', __DIR__ . '/../src')
        ->exclude([
            __DIR__ . '/../src/HttpKernel/*',
            __DIR__ . '/../src/Process/*',
        ]);

    $services->set(SymfonyStyleFactory::class);

    $services->set(SymfonyStyle::class)
        ->factory([ref(SymfonyStyleFactory::class), 'create']);

    $services->set(Filesystem::class);

    $services->set(SmartFileSystem::class);
};
