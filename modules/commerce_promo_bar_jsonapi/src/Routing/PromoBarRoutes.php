<?php

namespace Drupal\commerce_promo_bar_jsonapi\Routing;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Declares the promo bar JSON:API route.
 */
class PromoBarRoutes implements ContainerInjectionInterface {

  /**
   * Constructs a new PromoBarRoutes object.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resourceTypeRepository
   *   The JSON:API resource type repository.
   */
  public function __construct(
    protected ResourceTypeRepositoryInterface $resourceTypeRepository,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('jsonapi.resource_type.repository'));
  }

  /**
   * Declares GET /jsonapi/promo-bars.
   *
   * @return \Symfony\Component\Routing\RouteCollection
   *   The routes.
   */
  public function routes(): RouteCollection {
    $collection = new RouteCollection();
    $resource_type = $this->resourceTypeRepository->get('commerce_promo_bar', 'commerce_promo_bar');
    if (!$resource_type) {
      return $collection;
    }

    // The entity resource type satisfies the route build validator of
    // JSON:API Resources; the resource responds with its own "promo-bar"
    // type. Its name is read here because JSON:API Extras can rename it.
    $collection->add('commerce_promo_bar_jsonapi.promo_bars', (new Route('/%jsonapi%/promo-bars'))
      ->setMethods(['GET'])
      ->addDefaults([
        '_jsonapi_resource' => 'commerce_promo_bar_jsonapi.promo_bars',
        '_jsonapi_resource_types' => [$resource_type->getTypeName()],
      ])
      // What a customer sees is decided by the promo bar rules, as for the
      // promo bar block.
      ->setRequirement('_access', 'TRUE'));
    return $collection;
  }

}
