<?php

declare(strict_types=1);

namespace mglaman\DrupalObjectStorage\Test;

use Aws\MockHandler;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use mglaman\DrupalObjectStorage\Event\ObjectStorageConfigureEvent;
use mglaman\DrupalObjectStorage\StreamWrapper\ObjectStorageStreamWrapper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @covers \mglaman\DrupalObjectStorage\ServiceProvider::register
 */
final class ObjectStorageStreamWrapperTest extends IntegrationTestBase implements ServiceProviderInterface, EventSubscriberInterface
{
    protected function setUp(): void
    {
        $GLOBALS['conf']['container_service_providers']['test'] = $this;
        putenv('AWS_ACCESS_KEY_ID=abc');
        putenv('AWS_SECRET_ACCESS_KEY=123');
        putenv('AWS_DEFAULT_REGION=us-east-1');
        putenv('S3_BUCKET=abc123');
        putenv('S3_ENDPOINT=https://storage');
        putenv('S3_CNAME=cdn.storage');
        parent::setUp();
    }

    public function register(ContainerBuilder $container)
    {
        $container
          ->register('testing.object_storage_configure_subscriber', self::class)
          ->addTag('event_subscriber');
        $container->set('testing.object_storage_configure_subscriber', $this);
    }

    public static function getSubscribedEvents()
    {
        return [
          ObjectStorageConfigureEvent::class => 'onConfigureObjectStorage',
        ];
    }

    public function onConfigureObjectStorage(ObjectStorageConfigureEvent $event): void
    {
        $args = $event->getArgs();
        match ($this->getName()) {
            // @todo provide more stubs.
            default => $args['handler'] = new MockHandler([]),
        };
        $event->setArgs($args);
    }

    /**
     * @covers \mglaman\DrupalObjectStorage\StreamWrapper\ObjectStorageStreamWrapper::getType
     */
    public function testGetType(): void
    {
        self::assertEquals(
            StreamWrapperInterface::WRITE_VISIBLE,
            ObjectStorageStreamWrapper::getType()
        );
    }
}
