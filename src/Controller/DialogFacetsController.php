<?php

namespace Drupal\dialog_facets\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays a facet within a page for rendering as a dialog.
 */
class DialogFacetsController extends ControllerBase {

  /**
   * The facet manager.
   *
   * @var \Drupal\facets\FacetManager\DefaultFacetManager
   */
  protected $facetManager;

  /**
   * The entity storage used for facets.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $facetStorage;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->facetManager = $container->get('facets.manager');
    $instance->facetStorage = $container->get('entity_type.manager')->getStorage('facets_facet');
    return $instance;
  }

  /**
   * Displays a facet as a page.
   *
   * @param string $facet
   *   A facet ID.
   *
   * @return array
   *   A render array.
   */
  public function content($facet) {
    /** @var \Drupal\facets\FacetInterface $facet */
    $facet = $this->facetStorage->load($facet);

    // No need to build the facet if it does not need to be visible.
    if (empty($facet) || $facet->getOnlyVisibleWhenFacetSourceIsVisible() &&
      (!$facet->getFacetSource() || !$facet->getFacetSource()->isRenderedInCurrentRequest())) {
      return [];
    }

    return $this->facetManager->build($facet);
  }

  /**
   * Returns the title of a facet page.
   *
   * @param string $facet
   *   A facet ID.
   *
   * @return string
   *   The page title
   */
  public function title($facet) {
    /** @var \Drupal\facets\FacetInterface $facet */
    $facet = $this->facetStorage->load($facet);
    return $facet->label();
  }

}

