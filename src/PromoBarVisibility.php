<?php

namespace Drupal\commerce_promo_bar;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\commerce_promo_bar\Entity\PromoBarInterface;
use Drupal\path_alias\AliasManagerInterface;

/**
 * Evaluates the page visibility of promo bars.
 */
class PromoBarVisibility implements PromoBarVisibilityInterface {

  /**
   * Constructs a new PromoBarVisibility object.
   *
   * @param \Drupal\Core\Path\CurrentPathStack $currentPath
   *   The current path.
   * @param \Drupal\path_alias\AliasManagerInterface $aliasManager
   *   The path alias manager.
   * @param \Drupal\Core\Path\PathMatcherInterface $pathMatcher
   *   The path matcher.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    protected CurrentPathStack $currentPath,
    protected AliasManagerInterface $aliasManager,
    protected PathMatcherInterface $pathMatcher,
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function isVisible(PromoBarInterface $promo_bar, ?string $path = NULL): bool {
    if (empty($promo_bar->getPages())) {
      return TRUE;
    }

    // Convert path to lowercase. This allows comparison of the same path
    // with different case. Ex: /Page, /page, /PAGE.
    $pages = mb_strtolower($promo_bar->getPages());

    // True means show, false is hide.
    $visibility = (bool) $promo_bar->get('visibility')->value;

    $matches = FALSE;
    foreach ($this->getCandidatePaths($path) as $candidate) {
      if ($this->pathMatcher->matchPath($candidate, $pages)) {
        $matches = TRUE;
        break;
      }
    }
    return $matches ? $visibility : !$visibility;
  }

  /**
   * Gets the paths to match the promo bar pages against.
   *
   * @param string|null $path
   *   The page path, or NULL for the current request path.
   *
   * @return string[]
   *   The lowercase path, its alias and the internal path it is an alias of.
   */
  protected function getCandidatePaths(?string $path): array {
    if ($path === NULL) {
      // The current path is the internal path, the front page included.
      $path = $this->currentPath->getPath();
    }
    else {
      $path = '/' . ltrim(parse_url($path, PHP_URL_PATH) ?: '', '/');
    }
    // Do not trim a trailing slash if that is the complete path.
    $path = $path === '/' ? $path : rtrim($path, '/');

    $paths = [$path];
    if ($path === '/' && $front_page = $this->configFactory->get('system.site')->get('page.front')) {
      $paths[] = $front_page;
    }
    foreach ($paths as $internal_path) {
      $paths[] = $this->aliasManager->getAliasByPath($internal_path);
      $paths[] = $this->aliasManager->getPathByAlias($internal_path);
    }

    return array_values(array_unique(array_map('mb_strtolower', $paths)));
  }

}
