<?php

namespace Drupal\commerce_promo_bar\Form;

use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Defines the promo bar enable form.
 */
class PromoBarEnableForm extends ContentEntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to enable the promo bar %label?', [
      '%label' => $this->getEntity()->label(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Enable');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('entity.commerce_promo_bar.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_promo_bar\Entity\PromoBarInterface $promo_bar */
    $promo_bar = $this->getEntity();
    $promo_bar->setEnabled(TRUE);
    $promo_bar->save();
    $this->messenger()->addStatus($this->t('Successfully enabled the promo bar %label.', ['%label' => $promo_bar->label()]));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
