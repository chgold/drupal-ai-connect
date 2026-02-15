<?php

namespace Drupal\ai_connect\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class SettingsForm extends ConfigFormBase {

  protected function getEditableConfigNames() {
    return ['ai_connect.settings'];
  }

  public function getFormId() {
    return 'ai_connect_settings_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('ai_connect.settings');

    $form['jwt_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('JWT Secret'),
      '#description' => $this->t('Secret key for JWT token generation. Auto-generated on install.'),
      '#default_value' => $config->get('jwt_secret'),
      '#required' => TRUE,
      '#size' => 64,
    ];

    $form['token_expiry'] = [
      '#type' => 'number',
      '#title' => $this->t('Token Expiry (seconds)'),
      '#description' => $this->t('JWT token expiration time in seconds.'),
      '#default_value' => $config->get('token_expiry') ?? 3600,
      '#required' => TRUE,
      '#min' => 300,
      '#max' => 86400,
    ];

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

    $form['regenerate_secret'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Regenerate JWT Secret'),
      '#description' => $this->t('WARNING: This will invalidate all existing tokens.'),
      '#default_value' => FALSE,
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory()->getEditable('ai_connect.settings');

    if ($form_state->getValue('regenerate_secret')) {
      $config->set('jwt_secret', bin2hex(random_bytes(32)));
      \Drupal::messenger()->addWarning($this->t('JWT secret has been regenerated. All existing tokens are now invalid.'));
    }
    else {
      $config->set('jwt_secret', $form_state->getValue('jwt_secret'));
    }

    $config
      ->set('token_expiry', $form_state->getValue('token_expiry'))
      ->set('rate_limit_per_minute', $form_state->getValue('rate_limit_per_minute'))
      ->set('rate_limit_per_hour', $form_state->getValue('rate_limit_per_hour'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
