<?php

namespace Drupal\ai_connect\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * OAuth 2.0 service with PKCE support.
 *
 * Handles OAuth 2.0 authorization code flow with PKCE (Proof Key for
 * Code Exchange) for secure token management and validation.
 */
class OAuthService {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Default access token lifetime in seconds.
   *
   * @var int
   */
  protected $defaultTokenLifetime = 3600;

  /**
   * Default authorization code lifetime in seconds.
   *
   * @var int
   */
  protected $defaultCodeLifetime = 600;

  /**
   * Default refresh token lifetime in seconds.
   *
   * @var int
   */
  protected $defaultRefreshTokenLifetime = 2592000;

  /**
   * Constructs an OAuthService object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   */
  public function __construct(Connection $database, LoggerChannelFactoryInterface $logger_factory) {
    $this->database = $database;
    $this->logger = $logger_factory->get('ai_connect');
  }

  /**
   * Creates an authorization code for the OAuth 2.0 authorization code flow.
   *
   * @param string $client_id
   *   The client ID.
   * @param int $user_id
   *   The user ID.
   * @param string $redirect_uri
   *   The redirect URI.
   * @param string $code_challenge
   *   The PKCE code challenge.
   * @param string $code_challenge_method
   *   The PKCE code challenge method (e.g., 'S256').
   * @param array $scopes
   *   The requested scopes.
   *
   * @return string|false
   *   The authorization code, or FALSE on failure.
   */
  public function createAuthorizationCode($client_id, $user_id, $redirect_uri, $code_challenge, $code_challenge_method, array $scopes) {
    $code = $this->generateToken(128);
    $expires_at = time() + $this->defaultCodeLifetime;

    try {
      $this->database->insert('ai_connect_oauth_codes')
        ->fields([
          'code' => $code,
          'client_id' => $client_id,
          'user_id' => $user_id,
          'redirect_uri' => $redirect_uri,
          'code_challenge' => $code_challenge,
          'code_challenge_method' => $code_challenge_method,
          'scopes' => json_encode($scopes),
          'expires_at' => $expires_at,
        ])
        ->execute();

      return $code;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to create authorization code: @error', ['@error' => $e->getMessage()]);
      return FALSE;
    }
  }

  /**
   * Exchanges an authorization code for an access token.
   *
   * @param string $code
   *   The authorization code.
   * @param string $client_id
   *   The client ID.
   * @param string $code_verifier
   *   The PKCE code verifier.
   * @param string $redirect_uri
   *   The redirect URI.
   *
   * @return array
   *   An array containing the token data or error information.
   */
  public function exchangeCodeForToken($code, $client_id, $code_verifier, $redirect_uri) {
    $auth_code = $this->database->select('ai_connect_oauth_codes', 'c')
      ->fields('c')
      ->condition('code', $code)
      ->execute()
      ->fetchObject();

    if (!$auth_code) {
      return ['error' => 'invalid_grant', 'error_description' => 'Authorization code not found'];
    }

    if ($auth_code->used_at !== NULL) {
      return ['error' => 'invalid_grant', 'error_description' => 'Authorization code already used'];
    }

    if ($auth_code->expires_at < time()) {
      return ['error' => 'invalid_grant', 'error_description' => 'Authorization code expired'];
    }

    if ($auth_code->client_id !== $client_id) {
      return ['error' => 'invalid_client', 'error_description' => 'Client ID mismatch'];
    }

    if ($auth_code->redirect_uri !== $redirect_uri) {
      return ['error' => 'invalid_grant', 'error_description' => 'Redirect URI mismatch'];
    }

    if (!$this->verifyPkce($code_verifier, $auth_code->code_challenge, $auth_code->code_challenge_method)) {
      return ['error' => 'invalid_grant', 'error_description' => 'PKCE verification failed'];
    }

    $this->database->update('ai_connect_oauth_codes')
      ->fields(['used_at' => time()])
      ->condition('id', $auth_code->id)
      ->execute();

    $token = $this->createAccessToken(
      $auth_code->client_id,
      $auth_code->user_id,
      json_decode($auth_code->scopes, TRUE)
    );

    return $token;
  }

  /**
   * Creates an access token and refresh token.
   *
   * @param string $client_id
   *   The client ID.
   * @param int $user_id
   *   The user ID.
   * @param array $scopes
   *   The granted scopes.
   *
   * @return array|false
   *   An array containing the token data, or FALSE on failure.
   */
  public function createAccessToken($client_id, $user_id, array $scopes) {
    $token = 'dpc_' . $this->generateToken(64);
    $refresh_token = 'dpr_' . $this->generateToken(64);
    $expires_at = time() + $this->defaultTokenLifetime;
    $refresh_token_expires_at = time() + $this->defaultRefreshTokenLifetime;

    try {
      $this->database->insert('ai_connect_oauth_tokens')
        ->fields([
          'token' => $token,
          'refresh_token' => $refresh_token,
          'client_id' => $client_id,
          'user_id' => $user_id,
          'scopes' => json_encode($scopes),
          'expires_at' => $expires_at,
          'refresh_token_expires_at' => $refresh_token_expires_at,
          'created_at' => time(),
        ])
        ->execute();

      return [
        'access_token' => $token,
        'token_type' => 'Bearer',
        'expires_in' => $this->defaultTokenLifetime,
        'refresh_token' => $refresh_token,
        'refresh_token_expires_in' => $this->defaultRefreshTokenLifetime,
        'scope' => implode(' ', $scopes),
      ];
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to create access token: @error', ['@error' => $e->getMessage()]);
      return FALSE;
    }
  }

  /**
   * Validates an access token.
   *
   * @param string $token
   *   The access token to validate.
   *
   * @return array
   *   An array containing the token data or error information.
   */
  public function validateToken($token) {
    $token_data = $this->database->select('ai_connect_oauth_tokens', 't')
      ->fields('t')
      ->condition('token', $token)
      ->execute()
      ->fetchObject();

    if (!$token_data) {
      return ['error' => 'invalid_token', 'error_description' => 'Token not found'];
    }

    if ($token_data->revoked_at !== NULL) {
      return ['error' => 'invalid_token', 'error_description' => 'Token has been revoked'];
    }

    if ($token_data->expires_at < time()) {
      return ['error' => 'invalid_token', 'error_description' => 'Token expired'];
    }

    return [
      'user_id' => $token_data->user_id,
      'client_id' => $token_data->client_id,
      'scopes' => json_decode($token_data->scopes, TRUE),
    ];
  }

  /**
   * Exchanges a refresh token for a new access token.
   *
   * @param string $refresh_token
   *   The refresh token.
   * @param string $client_id
   *   The client ID.
   *
   * @return array
   *   An array containing the new token data or error information.
   */
  public function exchangeRefreshToken($refresh_token, $client_id) {
    $token_data = $this->database->select('ai_connect_oauth_tokens', 't')
      ->fields('t')
      ->condition('refresh_token', $refresh_token)
      ->execute()
      ->fetchObject();

    if (!$token_data) {
      return ['error' => 'invalid_grant', 'error_description' => 'Refresh token not found'];
    }

    if ($token_data->client_id !== $client_id) {
      return ['error' => 'invalid_client', 'error_description' => 'Client ID mismatch'];
    }

    if ($token_data->revoked_at !== NULL) {
      return ['error' => 'invalid_grant', 'error_description' => 'Refresh token has been revoked'];
    }

    if ($token_data->refresh_token_expires_at < time()) {
      return ['error' => 'invalid_grant', 'error_description' => 'Refresh token expired'];
    }

    $this->database->update('ai_connect_oauth_tokens')
      ->fields(['revoked_at' => time()])
      ->condition('id', $token_data->id)
      ->execute();

    $new_token = $this->createAccessToken(
      $token_data->client_id,
      $token_data->user_id,
      json_decode($token_data->scopes, TRUE)
    );

    return $new_token;
  }

  /**
   * Revokes an access token.
   *
   * @param string $token
   *   The access token to revoke.
   *
   * @return bool
   *   TRUE if the token was revoked successfully, FALSE otherwise.
   */
  public function revokeToken($token) {
    try {
      $this->database->update('ai_connect_oauth_tokens')
        ->fields(['revoked_at' => time()])
        ->condition('token', $token)
        ->execute();
      return TRUE;
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to revoke token: @error', ['@error' => $e->getMessage()]);
      return FALSE;
    }
  }

  /**
   * Verifies the PKCE code challenge.
   *
   * @param string $code_verifier
   *   The code verifier.
   * @param string $code_challenge
   *   The code challenge.
   * @param string $method
   *   The challenge method (e.g., 'S256').
   *
   * @return bool
   *   TRUE if the PKCE verification succeeds, FALSE otherwise.
   */
  protected function verifyPkce($code_verifier, $code_challenge, $method) {
    if ($method !== 'S256') {
      return FALSE;
    }

    $computed_challenge = $this->base64urlEncode(hash('sha256', $code_verifier, TRUE));

    return hash_equals($code_challenge, $computed_challenge);
  }

  /**
   * Validates a client ID.
   *
   * @param string $client_id
   *   The client ID to validate.
   *
   * @return bool
   *   TRUE if the client is valid, FALSE otherwise.
   */
  public function validateClient($client_id) {
    $client = $this->database->select('ai_connect_oauth_clients', 'c')
      ->fields('c')
      ->condition('client_id', $client_id)
      ->execute()
      ->fetchObject();

    return $client !== FALSE;
  }

  /**
   * Validates a redirect URI for a client.
   *
   * @param string $client_id
   *   The client ID.
   * @param string $redirect_uri
   *   The redirect URI to validate.
   *
   * @return bool
   *   TRUE if the redirect URI is valid for the client, FALSE otherwise.
   */
  public function validateRedirectUri($client_id, $redirect_uri) {
    $client = $this->database->select('ai_connect_oauth_clients', 'c')
      ->fields('c', ['redirect_uris'])
      ->condition('client_id', $client_id)
      ->execute()
      ->fetchObject();

    if (!$client) {
      return FALSE;
    }

    $allowed_uris = json_decode($client->redirect_uris, TRUE);
    return in_array($redirect_uri, $allowed_uris, TRUE);
  }

  /**
   * Validates requested scopes for a client.
   *
   * @param string $client_id
   *   The client ID.
   * @param array $requested_scopes
   *   The requested scopes.
   *
   * @return bool
   *   TRUE if all requested scopes are allowed for the client, FALSE otherwise.
   */
  public function validateScopes($client_id, array $requested_scopes) {
    $client = $this->database->select('ai_connect_oauth_clients', 'c')
      ->fields('c', ['allowed_scopes'])
      ->condition('client_id', $client_id)
      ->execute()
      ->fetchObject();

    if (!$client) {
      return FALSE;
    }

    $allowed_scopes = json_decode($client->allowed_scopes, TRUE);

    foreach ($requested_scopes as $scope) {
      if (!in_array($scope, $allowed_scopes, TRUE)) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Retrieves a client by ID.
   *
   * @param string $client_id
   *   The client ID.
   *
   * @return object|false
   *   The client object, or FALSE if not found.
   */
  public function getClient($client_id) {
    return $this->database->select('ai_connect_oauth_clients', 'c')
      ->fields('c')
      ->condition('client_id', $client_id)
      ->execute()
      ->fetchObject();
  }

  /**
   * Generates a random token.
   *
   * @param int $length
   *   The length of the token in bytes. Defaults to 64.
   *
   * @return string
   *   The generated token as a hexadecimal string.
   */
  protected function generateToken($length = 64) {
    return bin2hex(random_bytes($length / 2));
  }

  /**
   * Encodes data using base64url encoding.
   *
   * @param string $data
   *   The data to encode.
   *
   * @return string
   *   The base64url-encoded data.
   */
  protected function base64urlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
  }

}
