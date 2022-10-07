<?php

declare(strict_types=1);

namespace mglaman\DrupalObjectStorage\Test;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use mglaman\DrupalObjectStorage\Asset\AssetDumper;
use mglaman\DrupalObjectStorage\Asset\CssCollectionOptimizer;
use mglaman\DrupalObjectStorage\Asset\CssOptimizer;
use mglaman\DrupalObjectStorage\Asset\JsCollectionOptimizer;
use mglaman\DrupalObjectStorage\ServiceProvider;
use mglaman\DrupalObjectStorage\StreamWrapper\StreamWrapperManager;
use PHPUnit\Framework\TestCase;

/**
 * @covers \mglaman\DrupalObjectStorage\ServiceProvider::register
 */
final class ServiceProviderTest extends IntegrationTestBase
{
    /**
     * @dataProvider assetSchemeData
     */
    public function testAssetSchemeParameter(
        string $scheme,
        string $expected
    ): void {
        putenv("FILESYSTEM_DRIVER=$scheme");
        $this->kernel->rebuildContainer();
        self::assertEquals(
            $expected,
            $this->getContainer()->getParameter('asset_scheme')
        );
        putenv('FILESYSTEM_DRIVER=');
    }

    public function assetSchemeData(): \Iterator
    {
        yield ['', 's3'];
        yield ['s3', 's3'];
        yield ['public', 'public'];
        yield ['object', 'object'];
    }

    public function testDecoratedServices(): void
    {
        $decorated = [
          'stream_wrapper_manager' => StreamWrapperManager::class,
          'asset.css.dumper' => AssetDumper::class,
          'asset.js.dumper' => AssetDumper::class,
          'asset.css.collection_optimizer' => CssCollectionOptimizer::class,
          'asset.css.optimizer' => CssOptimizer::class,
          'asset.js.collection_optimizer' => JsCollectionOptimizer::class,
        ];
        foreach ($decorated as $id => $class) {
            self::assertInstanceOf(
                $class,
                $this->getContainer()->get($id)
            );
        }
    }
}
