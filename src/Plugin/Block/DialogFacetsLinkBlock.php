<?php

namespace Drupal\dialog_facets\Plugin\Block;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\facets\FacetManager\DefaultFacetManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block containing a link to a facet in a dialog.
 *
 * @Block(
 *   id = "dialog_facets_link_block",
 *   deriver = "Drupal\dialog_facets\Plugin\Block\DialogFacetsLinkBlockDeriver"
 * )
 */
class DialogFacetsLinkBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
   * Construct a DialogFacetsLinkBlock instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param string $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\facets\FacetManager\DefaultFacetManager $facet_manager
   *   The facet manager.
   * @param \Drupal\Core\Entity\EntityStorageInterface $facet_storage
   *   The entity storage used for facets.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, DefaultFacetManager $facet_manager, EntityStorageInterface $facet_storage) {
    $this->facetManager = $facet_manager;
    $this->facetStorage = $facet_storage;
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('facets.manager'),
      $container->get('entity_type.manager')->getStorage('facets_facet')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $config = [
      'dialog_type' => 'modal'
    ];

    /** @var \Drupal\facets\FacetInterface $facet */
    $facet = $this->facetStorage->load($this->getDerivativeId());
    $config['link_title'] = empty($facet) ? '' : $facet->label();

    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();

    $form['link_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link text'),
      '#default_value' => $config['link_title'],
    ];

    $form['dialog_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Dialog type'),
      '#options' => [
        'modal' => $this->t('Modal dialog'),
        'non_modal' => $this->t('Non-modal dialog'),
        'off_canvas' => $this->t('Off-canvas dialog'),
      ],
      '#default_value' => $config['dialog_type'] ?? 'modal',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $values = $form_state->getValues();
    $this->configuration['link_title'] = $values['link_title'];
    $this->configuration['dialog_type'] = $values['dialog_type'];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    /** @var \Drupal\facets\FacetInterface $facet */
    $facet = $this->facetStorage->load($this->getDerivativeId());
    if (empty($facet)) {
      return [];
    }

    $build['#cache'] = ['contexts' => ['url.query_args']];

    // In oder to know whether a facet dialog link should be displayed, the
    // facet must be processed to see if there are any results.  Unfortunately,
    // the DefaultFacetManager is kind of unfriendly toward what we're trying
    // to do.  We could call $this->facetManager->build($facet) and then
    // inspect the render array for result items, but that results in
    // unnecessary processing.  We can't call the manager's initFacets() to
    // start the processing because it's protected.  So it must be done
    // indirectly by calling getFacetsByFacetSourceId().  Even then, the array
    // must be re-keyed because the returned array indexes are not the facet
    // ids.  Then we can process the facets and inspect the number of results.
    $facets = $this->facetManager->getFacetsByFacetSourceId($facet->getFacetSourceId());
    $facets = $this->reKeyFacets($facets);
    $this->facetManager->processFacets($facet->getFacetSourceId());
    if (empty($facets[$facet->id()]->getResults())) {
      return $build;
    }

    // The current page's query parameters need to be added to the dialog link
    // or Facets won't have any context about existing searches.
    $query = \Drupal::request()->query;

    $build['link'] = [
      '#type' => 'link',
      '#title' => $this->configuration['link_title'],
      '#url' => Url::fromRoute('dialog_facets.facet', ['facet' => $facet->id()]),
      '#attributes' => [
        'class' => ['use-ajax'],
        'data-dialog-options' => Json::encode(['width' => 350]),
      ],
      '#options' => [
        'query' => $query->all(),
      ],
      '#attached' => [
        'library' => [
          'core/drupal.dialog.ajax',
        ],
      ],
    ];
    switch ($this->configuration['dialog_type']) {
      case 'non_modal':
        $build['link']['#attributes']['data-dialog-type'] = 'dialog';
        break;
      case 'off_canvas':
        $build['link']['#attributes']['data-dialog-type'] = 'dialog';
        $build['link']['#attributes']['data-dialog-renderer'] = 'off_canvas';
        break;
      case 'modal':
      default:
        $build['link']['#attributes']['data-dialog-type'] = 'modal';
        break;
    }

    return $build;
  }

  /**
   * Re-keys the array returned by DefaultFacetManager::getFacetsByFacetSourceId().
   *
   * @param \Drupal\facets\Entity\FacetInterface[]
   *   The array of facets whose keys are integers.
   *
   * @return \Drupal\facets\Entity\FacetInterface[]
   *   The array of facets whose keys are the facet IDs.
   */
  protected function reKeyFacets($facets) {
    $rekeyed_facets = [];
    foreach ($facets as $facet) {
      $rekeyed_facets[$facet->id()] = $facet;
    }
    return $rekeyed_facets;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    /** @var \Drupal\facets\FacetInterface $facet */
    $facet = $this->facetStorage->load($this->getDerivativeId());

    return ['config' => [$facet->getConfigDependencyName()]];
  }

}

