<?php

namespace Drupal\ai_connect\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Drupal\Core\Link;

class OAuthClientsController extends ControllerBase {

  protected $database;

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database')
    );
  }

  public function listClients() {
    $clients = $this->database->select('ai_connect_oauth_clients', 'c')
      ->fields('c')
      ->orderBy('created_at', 'DESC')
      ->execute()
      ->fetchAll();

    $header = [
      $this->t('Client ID'),
      $this->t('Name'),
      $this->t('Scopes'),
      $this->t('Created'),
      $this->t('Operations'),
    ];

    $rows = [];
    foreach ($clients as $client) {
      $scopes = json_decode($client->allowed_scopes, TRUE);
      $operations = [
        Link::createFromRoute($this->t('Edit'), 'ai_connect.oauth_client_edit', ['client_id' => $client->id]),
        Link::createFromRoute($this->t('Delete'), 'ai_connect.oauth_client_delete', ['client_id' => $client->id]),
      ];

      $rows[] = [
        $client->client_id,
        $client->client_name,
        implode(', ', $scopes),
        \Drupal::service('date.formatter')->format($client->created_at, 'short'),
        [
          'data' => [
            '#type' => 'operations',
            '#links' => [
              'edit' => [
                'title' => $this->t('Edit'),
                'url' => Url::fromRoute('ai_connect.oauth_client_edit', ['client_id' => $client->id]),
              ],
              'delete' => [
                'title' => $this->t('Delete'),
                'url' => Url::fromRoute('ai_connect.oauth_client_delete', ['client_id' => $client->id]),
              ],
            ],
          ],
        ],
      ];
    }

    $build = [];
    
    $build['description'] = [
      '#markup' => '<p>' . $this->t('Manage OAuth 2.0 clients that can access your Drupal site via the AI Connect API.') . '</p>',
    ];

    $build['add_link'] = [
      '#type' => 'link',
      '#title' => $this->t('Add OAuth Client'),
      '#url' => Url::fromRoute('ai_connect.oauth_client_add'),
      '#attributes' => [
        'class' => ['button', 'button--primary', 'button--small'],
      ],
      '#prefix' => '<p>',
      '#suffix' => '</p>',
    ];

    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No OAuth clients found. <a href=":url">Add a client</a>.', [
        ':url' => Url::fromRoute('ai_connect.oauth_client_add')->toString(),
      ]),
    ];

    $active_tokens = $this->database->select('ai_connect_oauth_tokens', 't')
      ->condition('revoked_at', NULL, 'IS NULL')
      ->condition('expires_at', time(), '>')
      ->countQuery()
      ->execute()
      ->fetchField();

    $build['stats'] = [
      '#markup' => '<p><strong>' . $this->t('Active Tokens:') . '</strong> ' . $active_tokens . '</p>',
    ];

    return $build;
  }

  public function deleteClient($client_id) {
    $client = $this->database->select('ai_connect_oauth_clients', 'c')
      ->fields('c')
      ->condition('id', $client_id)
      ->execute()
      ->fetchObject();

    if (!$client) {
      $this->messenger()->addError($this->t('OAuth client not found.'));
      return $this->redirect('ai_connect.oauth_clients');
    }

    return $this->formBuilder()->getForm('Drupal\ai_connect\Form\OAuthClientDeleteForm', $client);
  }

}
