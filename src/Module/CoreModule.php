<?php

namespace Drupal\ai_connect\Module;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Core Drupal module for AI Connect.
 */
class CoreModule extends ModuleBase {

  /**
   * Module name.
   *
   * @var string
   */
  protected $moduleName = 'drupal';

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a CoreModule object.
   *
   * @param \Drupal\ai_connect\Service\ManifestService $manifestService
   *   The manifest service.
   * @param \Drupal\Core\Database\Connection|null $database
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface|null $entity_type_manager
   *   The entity type manager.
   */
  public function __construct($manifestService, ?Connection $database = NULL, ?EntityTypeManagerInterface $entity_type_manager = NULL) {
    $this->database = $database ?? \Drupal::database();
    $this->entityTypeManager = $entity_type_manager ?? \Drupal::entityTypeManager();
    parent::__construct($manifestService);
  }

  /**
   * {@inheritdoc}
   */
  protected function registerTools() {
    $this->registerTool('searchNodes', [
      'description' => 'Search Drupal content nodes with filters',
      'input_schema' => [
        'type' => 'object',
        'properties' => [
          'search' => [
            'type' => 'string',
            'description' => 'Search query',
          ],
          'content_type' => [
            'type' => 'string',
            'description' => 'Content type machine name to filter by',
          ],
          'limit' => [
            'type' => 'integer',
            'description' => 'Maximum number of nodes',
            'default' => 10,
          ],
        ],
      ],
    ]);

    $this->registerTool('getNode', [
      'description' => 'Get a single node by ID',
      'input_schema' => [
        'type' => 'object',
        'required' => ['node_id'],
        'properties' => [
          'node_id' => [
            'type' => 'integer',
            'description' => 'Node ID',
          ],
        ],
      ],
    ]);

    $this->registerTool('searchComments', [
      'description' => 'Search Drupal comments',
      'input_schema' => [
        'type' => 'object',
        'properties' => [
          'search' => [
            'type' => 'string',
            'description' => 'Search query',
          ],
          'node_id' => [
            'type' => 'integer',
            'description' => 'Node ID to filter by',
          ],
          'limit' => [
            'type' => 'integer',
            'description' => 'Maximum number of comments',
            'default' => 10,
          ],
        ],
      ],
    ]);

    $this->registerTool('getComment', [
      'description' => 'Get a single comment by ID',
      'input_schema' => [
        'type' => 'object',
        'required' => ['comment_id'],
        'properties' => [
          'comment_id' => [
            'type' => 'integer',
            'description' => 'Comment ID',
          ],
        ],
      ],
    ]);

    $this->registerTool('getCurrentUser', [
      'description' => 'Get current authenticated user information',
      'input_schema' => [
        'type' => 'object',
        'properties' => [],
      ],
    ]);
  }

  /**
   * Executes searchNodes tool.
   *
   * @param array $params
   *   Tool parameters.
   *
   * @return array
   *   Tool result.
   */
  public function executeSearchNodes(array $params) {
    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('status', 1)
      ->accessCheck(FALSE)
      ->sort('created', 'DESC')
      ->range(0, $params['limit'] ?? 10);

    if (!empty($params['search'])) {
      $query->condition('title', '%' . $params['search'] . '%', 'LIKE');
    }

    if (!empty($params['content_type'])) {
      $query->condition('type', $params['content_type']);
    }

    $nids = $query->execute();
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);

    $result = [];
    foreach ($nodes as $node) {
      $result[] = $this->formatNode($node);
    }

    return $this->success($result);
  }

  /**
   * Executes getNode tool.
   *
   * @param array $params
   *   Tool parameters.
   *
   * @return array
   *   Tool result.
   */
  public function executeGetNode(array $params) {
    $node = $this->entityTypeManager->getStorage('node')->load($params['node_id']);

    if (!$node) {
      return $this->error('not_found', 'Node not found');
    }

    if (!$node->isPublished()) {
      return $this->error('not_accessible', 'Node is not published');
    }

    return $this->success($this->formatNode($node));
  }

  /**
   * Executes searchComments tool.
   *
   * @param array $params
   *   Tool parameters.
   *
   * @return array
   *   Tool result.
   */
  public function executeSearchComments(array $params) {
    $query = $this->entityTypeManager->getStorage('comment')->getQuery()
      ->condition('status', 1)
      ->accessCheck(FALSE)
      ->sort('created', 'DESC')
      ->range(0, $params['limit'] ?? 10);

    if (!empty($params['search'])) {
      $query->condition('subject', '%' . $params['search'] . '%', 'LIKE');
    }

    if (!empty($params['node_id'])) {
      $query->condition('entity_id', $params['node_id']);
    }

    $cids = $query->execute();
    $comments = $this->entityTypeManager->getStorage('comment')->loadMultiple($cids);

    $result = [];
    foreach ($comments as $comment) {
      $result[] = $this->formatComment($comment);
    }

    return $this->success($result);
  }

  /**
   * Executes getComment tool.
   *
   * @param array $params
   *   Tool parameters.
   *
   * @return array
   *   Tool result.
   */
  public function executeGetComment(array $params) {
    $comment = $this->entityTypeManager->getStorage('comment')->load($params['comment_id']);

    if (!$comment) {
      return $this->error('not_found', 'Comment not found');
    }

    if (!$comment->isPublished()) {
      return $this->error('not_accessible', 'Comment is not published');
    }

    return $this->success($this->formatComment($comment));
  }

  /**
   * Executes getCurrentUser tool.
   *
   * @param array $params
   *   Tool parameters.
   *
   * @return array
   *   Tool result.
   */
  public function executeGetCurrentUser(array $params) {
    $current_user = \Drupal::currentUser();

    if ($current_user->isAnonymous()) {
      return $this->error('not_authenticated', 'No authenticated user');
    }

    $user = $this->entityTypeManager->getStorage('user')->load($current_user->id());

    return $this->success([
      'user_id' => $user->id(),
      'username' => $user->getAccountName(),
      'email' => $user->getEmail(),
      'roles' => $user->getRoles(),
      'created' => $user->getCreatedTime(),
      'last_access' => $user->getLastAccessedTime(),
    ]);
  }

  /**
   * Formats a node for output.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   *
   * @return array
   *   Formatted node data.
   */
  protected function formatNode($node) {
    $url = $node->toUrl('canonical', ['absolute' => TRUE])->toString();

    return [
      'node_id' => $node->id(),
      'title' => $node->getTitle(),
      'content_type' => $node->bundle(),
      'user_id' => $node->getOwnerId(),
      'username' => $node->getOwner()->getAccountName(),
      'created' => date('c', $node->getCreatedTime()),
      'changed' => date('c', $node->getChangedTime()),
      'published' => $node->isPublished(),
      'url' => $url,
    ];
  }

  /**
   * Formats a comment for output.
   *
   * @param \Drupal\comment\CommentInterface $comment
   *   The comment entity.
   *
   * @return array
   *   Formatted comment data.
   */
  protected function formatComment($comment) {
    $url = $comment->toUrl('canonical', ['absolute' => TRUE])->toString();

    return [
      'comment_id' => $comment->id(),
      'subject' => $comment->getSubject(),
      'node_id' => $comment->getCommentedEntityId(),
      'user_id' => $comment->getOwnerId(),
      'username' => $comment->getOwner()->getAccountName(),
      'created' => date('c', $comment->getCreatedTime()),
      'published' => $comment->isPublished(),
      'url' => $url,
    ];
  }

}
