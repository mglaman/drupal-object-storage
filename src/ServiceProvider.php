<?php

declare(strict_types=1);

namespace mglaman\DrupalObjectStorage;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;
use mglaman\DrupalObjectStorage\Asset\AssetDumper;
use mglaman\DrupalObjectStorage\Asset\CssCollectionOptimizer;
use mglaman\DrupalObjectStorage\Asset\CssOptimizer;
use mglaman\DrupalObjectStorage\Asset\JsCollectionOptimizer;
use mglaman\DrupalObjectStorage\StreamWrapper\ObjectStorageStreamWrapper;
use mglaman\DrupalObjectStorage\StreamWrapper\StreamWrapperManager;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;

final class ServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $container): void
    {
        $assetScheme = getenv('FILESYSTEM_DRIVER');
        if (!is_string($assetScheme) || $assetScheme === '') {
            $assetScheme = 's3';
        }
        $container->setParameter('asset_scheme', $assetScheme);

        $streamWrapperManager = new ChildDefinition('stream_wrapper_manager');
        $streamWrapperManager->setClass(StreamWrapperManager::class);
        $streamWrapperManager->setDecoratedService('stream_wrapper_manager');
        $container->setDefinition('object_storage.stream_wrapper_manager', $streamWrapperManager);

        $container->register('stream_wrapper.s3', ObjectStorageStreamWrapper::class)
            ->addTag('stream_wrapper', ['scheme' => 's3']);

        $container->register('object_storage.asset_css_dump', AssetDumper::class)
            ->setDecoratedService('asset.css.dumper')
            ->setArguments([
              new Reference('file_system'),
              new Reference('config.factory'),
              new Parameter('asset_scheme'),
            ]);

        $container->register('object_storage.asset_js_dump', AssetDumper::class)
            ->setDecoratedService('asset.js.dumper')
            ->setArguments([
              new Reference('file_system'),
              new Reference('config.factory'),
              new Parameter('asset_scheme'),
            ]);

        $container->register('object_storage.css_collection_optimizer', CssCollectionOptimizer::class)
            ->setArguments([
              new Reference('object_storage.css_collection_optimizer.inner'),
              new Reference('datetime.time'),
              new Reference('config.factory'),
              new Reference('file_system'),
              new Parameter('asset_scheme'),
            ])
            ->setDecoratedService('asset.css.collection_optimizer');

        $cssOptimize = new ChildDefinition('asset.css.optimizer');
        $cssOptimize->setClass(CssOptimizer::class);
        $cssOptimize->setDecoratedService('asset.css.optimizer');
        $container->setDefinition('object_storage.css_optimize', $cssOptimize);

        $container->register('object_storage.js_collection_optimizer', JsCollectionOptimizer::class)
          ->setArguments([
            new Reference('object_storage.js_collection_optimizer.inner'),
            new Reference('datetime.time'),
            new Reference('config.factory'),
            new Reference('file_system'),
            new Parameter('asset_scheme'),
            ])
          ->setDecoratedService('asset.js.collection_optimizer');
    }
}
