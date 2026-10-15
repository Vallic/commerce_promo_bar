<?php

namespace Drupal\commerce_promo_bar;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\commerce_promo_bar\Entity\PromoBarInterface;

/**
 * Prepares the promo bar body for output.
 */
interface PromoBarBodyProcessorInterface {

  /**
   * Replaces the tokens in a promo bar text.
   *
   * @param string $text
   *   The text, such as the unfiltered body value.
   * @param \Drupal\commerce_promo_bar\Entity\PromoBarInterface $promo_bar
   *   The promo bar, available as the "commerce_promo_bar" token data.
   * @param \Drupal\Core\Render\BubbleableMetadata $bubbleable_metadata
   *   Collects the cacheability and attachments of the replacements.
   *
   * @return string
   *   The text with the tokens replaced.
   */
  public function replaceTokens(string $text, PromoBarInterface $promo_bar, BubbleableMetadata $bubbleable_metadata): string;

  /**
   * Processes the promo bar body outside of entity rendering.
   *
   * For API responses and other output that does not render the promo bar
   * entity. The tokens are replaced first, then the text format filters run,
   * as when the body is rendered.
   *
   * @param \Drupal\commerce_promo_bar\Entity\PromoBarInterface $promo_bar
   *   The promo bar, in the language to output.
   * @param \Drupal\Core\Render\BubbleableMetadata $bubbleable_metadata
   *   Collects the cacheability and attachments of the tokens and filters.
   *
   * @return string
   *   The filtered body markup, or an empty string if there is no body.
   */
  public function process(PromoBarInterface $promo_bar, BubbleableMetadata $bubbleable_metadata): string;

}
