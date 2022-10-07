<?php

declare(strict_types=1);

namespace mglaman\DrupalObjectStorage\StreamWrapper;

use Aws\Credentials\Credentials;
use Aws\LruArrayCache;
use Aws\MockHandler;
use Aws\S3\S3Client;
use Aws\S3\StreamWrapper;
use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\image\Entity\ImageStyle;
use mglaman\DrupalObjectStorage\Event\ObjectStorageConfigureEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
final class ObjectStorageStreamWrapper extends StreamWrapper implements StreamWrapperInterface, ConfigurableStreamWrapperInterface
{
    private string $uri;

    private function addBucketToPath(string $path): string
    {
        $split = explode('://', $path, 2);
        $path = $split[0] . '://' . getenv('S3_BUCKET') . '/' . $split[1];
        if (pathinfo($path, PATHINFO_EXTENSION) === '') {
            $path .= '/';
        }
        return $path;
    }

    /**
     * @return array<int|string, string|int>|false
     */
    public function url_stat($path, $flags): array|false
    {
        return parent::url_stat($this->addBucketToPath($path), $flags);
    }

    public function mkdir($path, $mode, $options)
    {
        $path = rtrim($this->addBucketToPath($path), '\/') . '/';
        return parent::mkdir($path, $mode, $options);
    }

    public function rmdir($path, $options)
    {
        $path = rtrim($this->addBucketToPath($path), '\/') . '/';
        return parent::rmdir($path, $options);
    }

    public function dir_opendir($path, $options)
    {
        return parent::dir_opendir($this->addBucketToPath($path), $options);
    }

    public function stream_open($path, $mode, $options, &$opened_path)
    {
        return parent::stream_open($this->addBucketToPath($path), $mode, $options, $opened_path);
    }

    public function rename($path_from, $path_to)
    {
      // @todo does this need `addBucketToPath`? Only if renaming within S3?
        return parent::rename($path_from, $path_to);
    }

    public function unlink($path)
    {
        return parent::unlink($this->addBucketToPath($path));
    }

    public function stream_lock($operation)
    {
        return true;
    }

    public function stream_metadata($path, $option, $value)
    {
        return match ($option) {
            STREAM_META_ACCESS => true,
            default => false,
        };
    }

    public function stream_set_option($option, $arg1, $arg2)
    {
        return false;
    }

    public function stream_truncate($new_size)
    {
        return false;
    }

    public static function getType(): int
    {
        return self::WRITE_VISIBLE;
    }

    public function getName(): string
    {
        return 'Object storage (S3)';
    }

    public function getDescription(): string
    {
        return 'Flysystem S3 stream wrapper for object storage support';
    }

    public function setUri($uri): void
    {
        $this->uri = $uri;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getExternalUrl(): string
    {
        $target = $this->getTarget($this->uri);
        if (str_starts_with($target, 'styles/') && !file_exists($this->uri)) {
            $this->generateImageStyle($target);
        }
        return 'https://' . getenv('S3_CNAME') . '/' . UrlHelper::encodePath($target);
    }

    public function realpath()
    {
        return false;
    }

    public function dirname($uri = null): string
    {
        if ($uri === null) {
            $uri = $this->uri;
        }
        $scheme = StreamWrapperManager::getScheme($uri);
        $dirname = dirname(StreamWrapperManager::getTarget($uri));
        if ($dirname === '.') {
            $dirname = '';
        }

        return "$scheme://$dirname";
    }

    private function getTarget(string $uri): string
    {
        return substr($uri, strpos($uri, '://') + 3);
    }

    public static function getContextDefaults(ContainerInterface $container): array
    {
        try {
          // @todo inject from factory from container.
          // @todo test (https://github.com/aws/aws-sdk-php/issues/2023, https://github.com/aws/aws-sdk-php/issues/1043)
            $args = [
            'version' => 'latest',
            'region' => getenv('AWS_DEFAULT_REGION'),
            'endpoint' => getenv('S3_ENDPOINT'),
            'use_path_style_endpoint' => getenv('S3_USE_PATH_STYLE_ENDPOINT') === 'true',
            'credentials' => new Credentials(
                getenv('AWS_ACCESS_KEY_ID'),
                getenv('AWS_SECRET_ACCESS_KEY'),
            ),
            ];
            // Support `test` and `testing` environment strings.
            if (str_starts_with($container->getParameter('kernel.environment'), 'test')) {
                $args['handler'] = new MockHandler([]);
            }
            $event = new ObjectStorageConfigureEvent($args);
            $container->get('event_dispatcher')->dispatch($event);
            $client = new S3Client($event->getArgs());
        } catch (\Exception $exception) {
            // @todo bubble error for production debugging.
            $client = null;
        }
        $defaults['client'] = $client;
      // @todo support Drupal's cache layers.
        $defaults['cache'] = new LruArrayCache();
        $defaults['ACL'] = 'public-read';
        return $defaults;
    }

    protected function generateImageStyle($target)
    {
        if (!str_starts_with($target, 'styles/') || substr_count($target, '/') < 3) {
            return false;
        }

        [, $style, $scheme, $file] = explode('/', $target, 4);

        if (!$image_style = ImageStyle::load($style)) {
            return false;
        }

        $image_uri = $scheme . '://' . $file;

        $derivative_uri = $image_style->buildUri($image_uri);

        if (!file_exists($image_uri)) {
            $path_info = pathinfo($image_uri);
            $converted_image_uri = $path_info['dirname'] . '/' . $path_info['filename'];

            if (!file_exists($converted_image_uri)) {
                return false;
            }

          // The converted file does exist, use it as the source.
            $image_uri = $converted_image_uri;
        }

        $lock_name = 'image_style_deliver:' . $image_style->id() . ':' . Crypt::hashBase64($image_uri);

        if (!file_exists($derivative_uri)) {
            $lock_acquired = \Drupal::lock()->acquire($lock_name);
            if (!$lock_acquired) {
                return false;
            }
        }

        $success = file_exists($derivative_uri) || $image_style->createDerivative($image_uri, $derivative_uri);

        if (!empty($lock_acquired)) {
            \Drupal::lock()->release($lock_name);
        }

        return $success;
    }
}
