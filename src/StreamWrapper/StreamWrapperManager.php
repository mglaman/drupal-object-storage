<?php

declare(strict_types=1);

namespace mglaman\DrupalObjectStorage\StreamWrapper;

use Drupal\Core\StreamWrapper\StreamWrapperManager as CoreStreamWrapperManager;

/**
 * Extends vs inner due to static methods on interface.
 */
final class StreamWrapperManager extends CoreStreamWrapperManager
{
  /**
   * @phpstan-param class-string $class
   */
    public function registerWrapper($scheme, $class, $type): void
    {
        parent::registerWrapper($scheme, $class, $type);
        if (is_a($class, ConfigurableStreamWrapperInterface::class, true)) {
            $default = stream_context_get_options(stream_context_get_default());
            $default[$scheme] = $class::getContextDefaults($this->container);
            stream_context_set_default($default);
        }
    }
}
