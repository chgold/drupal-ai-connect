<?php

namespace Drupal\ai_connect\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;

/**
 * Confirmation form for deleting an OAuth client.
 *
 * Displays a confirmation dialog before deleting an OAuth client and
 * revoking all associated tokens.
 */
class OAuthClientDeleteForm extends ConfirmFormBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The OAuth client object.
   *
   * @var object
   */
  protected $client;

  /**
   * Constructs an OAuthClientDeleteForm object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
          $container->get('database')
      );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_connect_oauth_client_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $client = NULL) {
    $this->client = $client;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t(
          'Are you sure you want to delete the OAuth client %name?', [
            '%name' => $this->client->client_name,
          ]
      );
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This will revoke all active tokens for this client. This action cannot be undone.');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('ai_connect.oauth_clients');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->database->update('ai_connect_oauth_tokens')
      ->fields(['revoked_at' => time()])
      ->condition('client_id', $this->client->client_id)
      ->execute();

    $this->database->delete('ai_connect_oauth_clients')
      ->condition('id', $this->client->id)
      ->execute();

    $this->messenger()->addStatus(
          $this->t(
              'OAuth client %name has been deleted.', [
                '%name' => $this->client->client_name,
              ]
          )
      );

    $form_state->setRedirect('ai_connect.oauth_clients');
  }

}
