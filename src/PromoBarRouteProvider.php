<?php

namespace Drupal\commerce_promo_bar;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\entity\Routing\AdminHtmlRouteProvider;
use Symfony\Component\Routing\Route;

/**
 * Provides routes for the promo bar entity.
 */
class PromoBarRouteProvider extends AdminHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $entity_type) {
    $collection = parent::getRoutes($entity_type);

    foreach (['enable', 'disable'] as $operation) {
      if ($form_route = $this->getPromotionFormRoute($entity_type, $operation)) {
        $collection->add('entity.commerce_promo_bar.' . $operation . '_form', $form_route);
      }
    }
    if ($entity_type->hasLinkTemplate('reorder')) {
      $reorder_form_route = $this->getCollectionRoute($entity_type);
      $reorder_form_route->setPath($entity_type->getLinkTemplate('reorder'));
      $collection->add('entity.commerce_promo_bar.reorder', $reorder_form_route);
    }

    return $collection;
  }

  /**
   * Gets a promo bar form route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param string $operation
   *   The 'operation' (e.g 'disable', 'enable').
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getPromotionFormRoute(EntityTypeInterface $entity_type, $operation) {
    if ($entity_type->hasLinkTemplate($operation . '-form')) {
      $route = new Route($entity_type->getLinkTemplate($operation . '-form'));
      $route
        ->addDefaults([
          '_entity_form' => "commerce_promo_bar.$operation",
          '_title_callback' => '\Drupal\Core\Entity\Controller\EntityController::title',
        ])
        ->setRequirement('_entity_access', 'commerce_promo_bar.update')
        ->setOption('parameters', [
          'commerce_promo_bar' => [
            'type' => 'entity:commerce_promo_bar',
          ],
        ])
        ->setRequirement('commerce_promo_bar', '\d+')
        ->setOption('_admin_route', TRUE);

      return $route;
    }
  }

}
