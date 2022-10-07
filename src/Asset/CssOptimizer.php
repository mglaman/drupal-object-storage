<?php

declare(strict_types=1);

namespace mglaman\DrupalObjectStorage\Asset;

use Drupal\Core\Asset\CssOptimizer as CoreCssOptimizer;

final class CssOptimizer extends CoreCssOptimizer
{
  /**
   * @todo this needs refactor when color removed in drupal core release.
   *
   * @phpstan-param array<int, string> $matches
   */
    public function rewriteFileURI($matches): string
    {
      // Prefix with base and remove '../' segments where possible.
        $path = $this->rewriteFileURIBasePath . $matches[1];
        $last = '';
        while ($path !== $last) {
            $last = $path;
            $path = preg_replace('`(^|/)(?!\.\./)([^/]+)/\.\./`', '$1', $path);
            if ($path === null) {
                return '';
            }
        }
      // Switched generateString to generateAbsoluteString.
        return 'url(' . $this->fileUrlGenerator->generateAbsoluteString($path) . ')';
    }
}
