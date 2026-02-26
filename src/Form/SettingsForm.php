<?php

namespace Drupal\ai_connect\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for AI Connect settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['ai_connect.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_connect_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('ai_connect.settings');

    $form['rate_limit_per_minute'] = [
      '#type' => 'number',
      '#title' => $this->t('Rate Limit Per Minute'),
      '#description' => $this->t('Maximum requests per minute per user.'),
      '#default_value' => $config->get('rate_limit_per_minute') ?? 50,
      '#required' => TRUE,
      '#min' => 1,
      '#max' => 1000,
    ];

    $form['rate_limit_per_hour'] = [
      '#type' => 'number',
      '#title' => $this->t('Rate Limit Per Hour'),
      '#description' => $this->t('Maximum requests per hour per user.'),
      '#default_value' => $config->get('rate_limit_per_hour') ?? 1000,
      '#required' => TRUE,
      '#min' => 10,
      '#max' => 100000,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory()->getEditable('ai_connect.settings');

    $config
      ->set('rate_limit_per_minute', $form_state->getValue('rate_limit_per_minute'))
      ->set('rate_limit_per_hour', $form_state->getValue('rate_limit_per_hour'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
