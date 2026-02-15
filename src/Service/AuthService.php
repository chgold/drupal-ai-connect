<?php

namespace Drupal\ai_connect\Service;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\user\UserAuthInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Authentication service for AI Connect.
 */
class AuthService {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The user authentication service.
   *
   * @var \Drupal\user\UserAuthInterface
   */
  protected $userAuth;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs an AuthService object.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    Connection $database,
    UserAuthInterface $user_auth,
    EntityTypeManagerInterface $entity_type_manager,
    RequestStack $request_stack
  ) {
    $this->configFactory = $config_factory;
    $this->database = $database;
    $this->userAuth = $user_auth;
    $this->entityTypeManager = $entity_type_manager;
    $this->requestStack = $request_stack;
  }

  /**
   * Generate JWT access token.
   */
  public function generateAccessToken($userId, array $scopes = ['read', 'write']) {
    $config = $this->configFactory->get('ai_connect.settings');
    $secret = $config->get('jwt_secret');
    $expiry = $config->get('token_expiry') ?? 3600;

    if (empty($secret)) {
      throw new \RuntimeException('JWT secret not configured');
    }

    $request = $this->requestStack->getCurrentRequest();
    $baseUrl = $request->getSchemeAndHttpHost();

    $payload = [
      'iss' => $baseUrl,
      'iat' => time(),
      'exp' => time() + $expiry,
      'user_id' => $userId,
      'scopes' => $scopes,
    ];

    return JWT::encode($payload, $secret, 'HS256');
  }

  /**
   * Validate JWT access token.
   */
  public function validateAccessToken($token) {
    try {
      $config = $this->configFactory->get('ai_connect.settings');
      $secret = $config->get('jwt_secret');

      if (empty($secret)) {
        return [
          'valid' => FALSE,
          'error' => 'JWT secret not configured',
        ];
      }

      $decoded = JWT::decode($token, new Key($secret, 'HS256'));

      return [
        'valid' => TRUE,
        'user_id' => $decoded->user_id,
        'scopes' => $decoded->scopes ?? ['read'],
      ];
    }
    catch (\Exception $e) {
      return [
        'valid' => FALSE,
        'error' => $e->getMessage(),
      ];
    }
  }

  /**
   * Generate API key.
   */
  public function generateApiKey($userId, $name, array $scopes = ['read', 'write']) {
    $apiKey = bin2hex(random_bytes(32));

    $this->database->insert('ai_connect_api_keys')
      ->fields([
        'user_id' => $userId,
        'api_key' => $apiKey,
        'name' => $name,
        'scopes' => serialize($scopes),
        'is_active' => 1,
        'created' => time(),
      ])
      ->execute();

    return $apiKey;
  }

  /**
   * Validate API key.
   */
  public function validateApiKey($apiKey) {
    $key = $this->database->select('ai_connect_api_keys', 'k')
      ->fields('k')
      ->condition('api_key', $apiKey)
      ->condition('is_active', 1)
      ->execute()
      ->fetchAssoc();

    if (!$key) {
      return ['valid' => FALSE];
    }

    $this->database->update('ai_connect_api_keys')
      ->fields(['last_used' => time()])
      ->condition('api_key_id', $key['api_key_id'])
      ->execute();

    return [
      'valid' => TRUE,
      'user_id' => $key['user_id'],
      'scopes' => unserialize($key['scopes']),
    ];
  }

  /**
   * Authenticate user with username/password.
   */
  public function authenticateUser($username, $password) {
    $uid = $this->userAuth->authenticate($username, $password);

    if (!$uid) {
      return [
        'success' => FALSE,
        'error' => 'Invalid credentials',
      ];
    }

    $blocked = $this->database->select('ai_connect_blocked_users', 'b')
      ->fields('b', ['user_id'])
      ->condition('user_id', $uid)
      ->execute()
      ->fetchField();

    if ($blocked) {
      return [
        'success' => FALSE,
        'error' => 'Access denied - user blocked from API access',
      ];
    }

    $user = $this->entityTypeManager->getStorage('user')->load($uid);

    if (!$user) {
      return [
        'success' => FALSE,
        'error' => 'User not found',
      ];
    }

    $config = $this->configFactory->get('ai_connect.settings');
    $expiry = $config->get('token_expiry') ?? 3600;

    $accessToken = $this->generateAccessToken($uid);

    return [
      'success' => TRUE,
      'access_token' => $accessToken,
      'token_type' => 'Bearer',
      'expires_in' => $expiry,
      'user_id' => $uid,
      'username' => $user->getAccountName(),
    ];
  }

}
