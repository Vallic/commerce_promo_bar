<?php

namespace Drupal\commerce_promo_bar;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Utility\Token;
use Drupal\commerce_promo_bar\Entity\PromoBarInterface;

/**
 * Prepares the promo bar body for output.
 */
class PromoBarBodyProcessor implements PromoBarBodyProcessorInterface {

  /**
   * Constructs a new PromoBarBodyProcessor object.
   *
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(
    protected Token $token,
    protected RendererInterface $renderer,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function replaceTokens(string $text, PromoBarInterface $promo_bar, BubbleableMetadata $bubbleable_metadata): string {
    return $this->token->replace($text, ['commerce_promo_bar' => $promo_bar], [
      'langcode' => $promo_bar->language()->getId(),
    ], $bubbleable_metadata);
  }

  /**
   * {@inheritdoc}
   */
  public function process(PromoBarInterface $promo_bar, BubbleableMetadata $bubbleable_metadata): string {
    $body = $promo_bar->get('body')->first();
    if (!$body || $body->isEmpty()) {
      return '';
    }

    $build = [
      '#type' => 'processed_text',
      '#text' => $this->replaceTokens((string) $body->get('value')->getValue(), $promo_bar, $bubbleable_metadata),
      '#format' => $body->get('format')->getValue(),
      '#langcode' => $promo_bar->language()->getId(),
    ];
    $markup = (string) $this->renderer->renderInIsolation($build);

    $filter_metadata = BubbleableMetadata::createFromRenderArray($build);
    $bubbleable_metadata->addCacheableDependency($filter_metadata);
    $bubbleable_metadata->addAttachments($filter_metadata->getAttachments());

    return $markup;
  }

}
