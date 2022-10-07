<?php

declare(strict_types=1);

namespace mglaman\DrupalObjectStorage\Test;

use Aws\LruArrayCache;
use Aws\S3\S3Client;

final class StreamWrapperManagerTest extends IntegrationTestBase
{
    /**
     * @covers \mglaman\DrupalObjectStorage\StreamWrapper\StreamWrapperManager::registerWrapper
     * @covers \mglaman\DrupalObjectStorage\ServiceProvider::register
     * @covers \mglaman\DrupalObjectStorage\StreamWrapper\ObjectStorageStreamWrapper::getContextDefaults
     * @covers \mglaman\DrupalObjectStorage\StreamWrapper\ObjectStorageStreamWrapper::getType
     */
    public function testRegisterWrapper(): void
    {
        putenv('AWS_DEFAULT_REGION=us-east-1');
        putenv('S3_ENDPOINT=foo');
        putenv('AWS_ACCESS_KEY_ID=abc');
        putenv('AWS_SECRET_ACCESS_KEY=123');
        // This is when Drupal registers stream wrappers.
        $this->refreshStreamWrappers();
        $default = stream_context_get_options(stream_context_get_default());
        self::assertArrayHasKey('s3', $default);
        self::assertInstanceOf(S3Client::class, $default['s3']['client']);
        self::assertInstanceOf(LruArrayCache::class, $default['s3']['cache']);
        self::assertEquals('public-read', $default['s3']['ACL']);
    }
}
