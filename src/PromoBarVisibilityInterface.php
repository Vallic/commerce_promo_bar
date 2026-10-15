<?php

namespace Drupal\commerce_promo_bar;

use Drupal\commerce_promo_bar\Entity\PromoBarInterface;

/**
 * Evaluates the page visibility of promo bars.
 */
interface PromoBarVisibilityInterface {

  /**
   * Checks whether a promo bar is visible on a page.
   *
   * The promo bar pages are matched against the path, its alias and the
   * internal path it is an alias of, ignoring case. "<front>" matches the
   * front page, and "/" counts as the front page.
   *
   * @param \Drupal\commerce_promo_bar\Entity\PromoBarInterface $promo_bar
   *   The promo bar.
   * @param string|null $path
   *   The page path, such as "/blog" or "/node/1", without the language
   *   prefix. A query string or fragment is ignored. NULL for the current
   *   request path.
   *
   * @return bool
   *   TRUE if the promo bar is visible on the page, FALSE otherwise.
   */
  public function isVisible(PromoBarInterface $promo_bar, ?string $path = NULL): bool;

}
