<?php

namespace Drupal\ai_connect\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;

class OAuthClientForm extends FormBase {

  protected $database;

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database')
    );
  }

  public function getFormId() {
    return 'ai_connect_oauth_client_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $client_id = NULL) {
    $client = NULL;
    
    if ($client_id) {
      $client = $this->database->select('ai_connect_oauth_clients', 'c')
        ->fields('c')
        ->condition('id', $client_id)
        ->execute()
        ->fetchObject();
    }

    $form['client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client ID'),
      '#required' => TRUE,
      '#default_value' => $client ? $client->client_id : '',
      '#disabled' => $client ? TRUE : FALSE,
      '#description' => $this->t('Unique identifier for this OAuth client.'),
    ];

    $form['client_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client Name'),
      '#required' => TRUE,
      '#default_value' => $client ? $client->client_name : '',
      '#description' => $this->t('Human-readable name for this client.'),
    ];

    $redirect_uris = $client ? json_decode($client->redirect_uris, TRUE) : ['urn:ietf:wg:oauth:2.0:oob'];
    $form['redirect_uris'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Redirect URIs'),
      '#required' => TRUE,
      '#default_value' => implode("\n", $redirect_uris),
      '#description' => $this->t('One URI per line. Use urn:ietf:wg:oauth:2.0:oob for out-of-band flow.'),
    ];

    $allowed_scopes = $client ? json_decode($client->allowed_scopes, TRUE) : ['read', 'write'];
    $form['allowed_scopes'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Allowed Scopes'),
      '#options' => [
        'read' => $this->t('Read - View content and settings'),
        'write' => $this->t('Write - Create and modify content'),
        'delete' => $this->t('Delete - Remove content'),
      ],
      '#default_value' => $allowed_scopes,
      '#description' => $this->t('Permissions this client can request.'),
    ];

    $form['id'] = [
      '#type' => 'hidden',
      '#value' => $client ? $client->id : NULL,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $client ? $this->t('Update Client') : $this->t('Create Client'),
      '#button_type' => 'primary',
    ];

    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => \Drupal\Core\Url::fromRoute('ai_connect.oauth_clients'),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $client_id = $form_state->getValue('client_id');
    $id = $form_state->getValue('id');

    $existing = $this->database->select('ai_connect_oauth_clients', 'c')
      ->fields('c', ['id'])
      ->condition('client_id', $client_id)
      ->execute()
      ->fetchField();

    if ($existing && (!$id || $existing != $id)) {
      $form_state->setErrorByName('client_id', $this->t('A client with this ID already exists.'));
    }

    $redirect_uris = $form_state->getValue('redirect_uris');
    $uris = array_filter(array_map('trim', explode("\n", $redirect_uris)));
    
    if (empty($uris)) {
      $form_state->setErrorByName('redirect_uris', $this->t('At least one redirect URI is required.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $redirect_uris = array_filter(array_map('trim', explode("\n", $form_state->getValue('redirect_uris'))));
    $allowed_scopes = array_values(array_filter($form_state->getValue('allowed_scopes')));

    $fields = [
      'client_id' => $form_state->getValue('client_id'),
      'client_name' => $form_state->getValue('client_name'),
      'redirect_uris' => json_encode($redirect_uris),
      'allowed_scopes' => json_encode($allowed_scopes),
    ];

    $id = $form_state->getValue('id');

    if ($id) {
      $this->database->update('ai_connect_oauth_clients')
        ->fields($fields)
        ->condition('id', $id)
        ->execute();

      $this->messenger()->addStatus($this->t('OAuth client updated successfully.'));
    }
    else {
      $fields['created_at'] = time();
      
      $this->database->insert('ai_connect_oauth_clients')
        ->fields($fields)
        ->execute();

      $this->messenger()->addStatus($this->t('OAuth client created successfully.'));
    }

    $form_state->setRedirect('ai_connect.oauth_clients');
  }

}
