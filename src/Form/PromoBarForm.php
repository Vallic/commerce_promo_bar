<?php

namespace Drupal\commerce_promo_bar\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\entity\Form\EntityDuplicateFormTrait;

/**
 * Form controller for the commerce promo bar entity edit forms.
 */
class PromoBarForm extends ContentEntityForm {

  use EntityDuplicateFormTrait;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Skip building the form if there are no available stores.
    $store_query = $this->entityTypeManager->getStorage('commerce_store')->getQuery()->accessCheck(TRUE);
    if ($store_query->count()->execute() == 0) {
      $link = Link::createFromRoute('Add a new store.', 'entity.commerce_store.add_page');
      $form['warning'] = [
        '#markup' => $this->t("Promo bar can't be created until a store has been added. @link", ['@link' => $link->toString()]),
      ];
      return $form;
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['#theme'] = ['commerce_promo_bar_form'];
    $form['#attached']['library'][] = 'commerce_promo_bar/form';
    $form['advanced'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['entity-meta']],
      '#weight' => 99,
    ];
    $form['option_details'] = [
      '#type' => 'container',
      '#title' => $this->t('Options'),
      '#group' => 'advanced',
      '#attributes' => ['class' => ['entity-meta__header']],
      '#weight' => -100,
    ];
    $form['date_details'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Dates'),
      '#group' => 'advanced',
    ];
    $form['visibility_details'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Other restrictions'),
      '#group' => 'advanced',
    ];

    $field_details_mapping = [
      'status' => 'option_details',
      'weight' => 'option_details',
      'stores' => 'option_details',
      'start_date' => 'date_details',
      'end_date' => 'date_details',
      'countdown_date' => 'date_details',
      'customer_roles' => 'visibility_details',
      'visibility' => 'visibility_details',
      'pages' => 'visibility_details',
    ];

    foreach ($field_details_mapping as $field => $group) {
      if (isset($form[$field])) {
        $form[$field]['#group'] = $group;
      }
    }

    if (isset($form['body'])) {
      $form['body']['tokens']['list'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => ['commerce_promo_bar'],
        '#show_restricted' => FALSE,
        '#global_types' => TRUE,
      ];
    }

    // Remove the '- None -' option from the customer roles dropdown.
    if ($form['customer_roles']['widget']['#type'] == 'select') {
      unset($form['customer_roles']['widget']['#options']['_none']);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $result = parent::save($form, $form_state);

    $entity = $this->getEntity();

    $message_arguments = ['%label' => $entity->toLink()->toString()];
    $logger_arguments = [
      '%label' => $entity->label(),
      'link' => $entity->toLink($this->t('View'))->toString(),
    ];

    switch ($result) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('New promo bar %label has been created.', $message_arguments));
        $this->logger('commerce_promo_bar')->notice('Created new promo bar %label', $logger_arguments);
        break;

      case SAVED_UPDATED:
        $this->messenger()->addStatus($this->t('The promo bar %label has been updated.', $message_arguments));
        $this->logger('commerce_promo_bar')->notice('Updated promo bar %label.', $logger_arguments);
        break;
    }

    $form_state->setRedirect('entity.commerce_promo_bar.collection');

    return $result;
  }

}
