<?php

namespace Drupal\Tests\ai_connect\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests OAuth 2.0 authorization code flow with PKCE.
 *
 * @group ai_connect
 */
class OAuthFlowTest extends BrowserTestBase {

  /**
   * Default theme.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['ai_connect', 'user', 'system'];

  /**
   * Test user account.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $testUser;

  /**
   * OAuth service instance.
   *
   * @var \Drupal\ai_connect\Service\OAuthService
   */
  protected $oauthService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->testUser = $this->drupalCreateUser([
      'access content',
      'administer ai connect',
    ]);

    $this->oauthService = \Drupal::service('ai_connect.oauth_service');
  }

  /**
   * Tests OAuth authorization endpoint requires login.
   */
  public function testOauthAuthorizationEndpointRequiresLogin() {
    $this->drupalGet('/oauth/authorize', [
      'query' => [
        'client_id' => 'test_client',
        'redirect_uri' => 'urn:ietf:wg:oauth:2.0:oob',
        'response_type' => 'code',
        'state' => 'test_state',
        'code_challenge' => 'test_challenge',
        'code_challenge_method' => 'S256',
      ],
    ]);

    $this->assertSession()->addressMatches('/\/user\/login/');
  }

  /**
   * Tests OAuth authorization with PKCE code challenge.
   */
  public function testOauthAuthorizationWithPkce() {
    $this->drupalLogin($this->testUser);

    $codeChallenge = $this->generateCodeChallenge('test_verifier_123456789012345678901234567890');
    $clientId = 'test_client_' . time();

    $this->drupalGet('/oauth/authorize', [
      'query' => [
        'client_id' => $clientId,
        'redirect_uri' => 'urn:ietf:wg:oauth:2.0:oob',
        'response_type' => 'code',
        'scope' => 'read write',
        'state' => 'test_state',
        'code_challenge' => $codeChallenge,
        'code_challenge_method' => 'S256',
      ],
    ]);

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains($clientId);
  }

  /**
   * Tests OAuth authorization requires PKCE.
   */
  public function testOauthAuthorizationRequiresPkce() {
    $this->drupalLogin($this->testUser);

    $this->drupalGet('/oauth/authorize', [
      'query' => [
        'client_id' => 'test_client',
        'redirect_uri' => 'urn:ietf:wg:oauth:2.0:oob',
        'response_type' => 'code',
        'state' => 'test_state',
      ],
    ]);

    $this->assertSession()->statusCodeEquals(400);
    $this->assertSession()->pageTextContains('PKCE required');
  }

  /**
   * Tests OAuth token exchange with valid authorization code.
   */
  public function testOauthTokenExchangeWithValidCode() {
    $this->drupalLogin($this->testUser);

    $codeVerifier = 'test_verifier_' . bin2hex(random_bytes(16));
    $codeChallenge = $this->generateCodeChallenge($codeVerifier);
    $clientId = 'test_client_' . time();
    $redirectUri = 'urn:ietf:wg:oauth:2.0:oob';

    $code = $this->oauthService->createAuthorizationCode(
      $clientId,
      $this->testUser->id(),
      $redirectUri,
      $codeChallenge,
      'S256',
      ['read', 'write']
    );

    $this->assertNotEmpty($code);

    $response = $this->drupalPost('/api/ai-connect/v1/oauth/token', [
      'grant_type' => 'authorization_code',
      'code' => $code,
      'client_id' => $clientId,
      'redirect_uri' => $redirectUri,
      'code_verifier' => $codeVerifier,
    ]);

    $data = json_decode($response, TRUE);
    $this->assertArrayHasKey('access_token', $data);
    $this->assertArrayHasKey('refresh_token', $data);
    $this->assertArrayHasKey('expires_in', $data);
    $this->assertEquals('Bearer', $data['token_type']);
  }

  /**
   * Tests OAuth token exchange rejects wrong PKCE verifier.
   */
  public function testOauthTokenExchangeRejectsWrongPkce() {
    $this->drupalLogin($this->testUser);

    $codeChallenge = $this->generateCodeChallenge('correct_verifier_12345678901234567890');
    $clientId = 'test_client_' . time();
    $redirectUri = 'urn:ietf:wg:oauth:2.0:oob';

    $code = $this->oauthService->createAuthorizationCode(
      $clientId,
      $this->testUser->id(),
      $redirectUri,
      $codeChallenge,
      'S256',
      ['read']
    );

    $response = $this->drupalPost('/api/ai-connect/v1/oauth/token', [
      'grant_type' => 'authorization_code',
      'code' => $code,
      'client_id' => $clientId,
      'redirect_uri' => $redirectUri,
      'code_verifier' => 'wrong_verifier_98765432109876543210',
    ]);

    $data = json_decode($response, TRUE);
    $this->assertArrayHasKey('error', $data);
    $this->assertEquals('invalid_grant', $data['error']);
  }

  /**
   * Tests OAuth refresh token flow.
   */
  public function testOauthRefreshToken() {
    $this->drupalLogin($this->testUser);

    $codeVerifier = 'test_verifier_' . bin2hex(random_bytes(16));
    $codeChallenge = $this->generateCodeChallenge($codeVerifier);
    $clientId = 'test_client_' . time();
    $redirectUri = 'urn:ietf:wg:oauth:2.0:oob';

    $code = $this->oauthService->createAuthorizationCode(
      $clientId,
      $this->testUser->id(),
      $redirectUri,
      $codeChallenge,
      'S256',
      ['read']
    );

    $tokenResponse = $this->drupalPost('/api/ai-connect/v1/oauth/token', [
      'grant_type' => 'authorization_code',
      'code' => $code,
      'client_id' => $clientId,
      'redirect_uri' => $redirectUri,
      'code_verifier' => $codeVerifier,
    ]);

    $tokenData = json_decode($tokenResponse, TRUE);
    $refreshToken = $tokenData['refresh_token'];

    $refreshResponse = $this->drupalPost('/api/ai-connect/v1/oauth/token', [
      'grant_type' => 'refresh_token',
      'refresh_token' => $refreshToken,
      'client_id' => $clientId,
    ]);

    $refreshData = json_decode($refreshResponse, TRUE);
    $this->assertArrayHasKey('access_token', $refreshData);
    $this->assertNotEquals($tokenData['access_token'], $refreshData['access_token']);
  }

  /**
   * Tests OAuth token revocation.
   */
  public function testOauthTokenRevocation() {
    $this->drupalLogin($this->testUser);

    $codeVerifier = 'test_verifier_' . bin2hex(random_bytes(16));
    $codeChallenge = $this->generateCodeChallenge($codeVerifier);
    $clientId = 'test_client_' . time();
    $redirectUri = 'urn:ietf:wg:oauth:2.0:oob';

    $code = $this->oauthService->createAuthorizationCode(
      $clientId,
      $this->testUser->id(),
      $redirectUri,
      $codeChallenge,
      'S256',
      ['read']
    );

    $tokenResponse = $this->drupalPost('/api/ai-connect/v1/oauth/token', [
      'grant_type' => 'authorization_code',
      'code' => $code,
      'client_id' => $clientId,
      'redirect_uri' => $redirectUri,
      'code_verifier' => $codeVerifier,
    ]);

    $tokenData = json_decode($tokenResponse, TRUE);
    $accessToken = $tokenData['access_token'];

    $revokeResponse = $this->drupalPost('/api/ai-connect/v1/oauth/revoke', [
      'token' => $accessToken,
    ]);

    $revokeData = json_decode($revokeResponse, TRUE);
    $this->assertTrue($revokeData['success']);

    $validateResult = $this->oauthService->validateToken($accessToken);
    $this->assertArrayHasKey('error', $validateResult);
  }

  /**
   * Tests tools endpoint requires authentication.
   */
  public function testToolsEndpointRequiresAuthentication() {
    $this->drupalPost('/api/ai-connect/v1/tools/drupal.getCurrentUser', []);

    $this->assertSession()->statusCodeEquals(401);
  }

  /**
   * Tests tools endpoint with valid OAuth token.
   */
  public function testToolsEndpointWithValidToken() {
    $this->drupalLogin($this->testUser);

    $codeVerifier = 'test_verifier_' . bin2hex(random_bytes(16));
    $codeChallenge = $this->generateCodeChallenge($codeVerifier);
    $clientId = 'test_client_' . time();
    $redirectUri = 'urn:ietf:wg:oauth:2.0:oob';

    $code = $this->oauthService->createAuthorizationCode(
      $clientId,
      $this->testUser->id(),
      $redirectUri,
      $codeChallenge,
      'S256',
      ['read', 'write']
    );

    $tokenResponse = $this->drupalPost('/api/ai-connect/v1/oauth/token', [
      'grant_type' => 'authorization_code',
      'code' => $code,
      'client_id' => $clientId,
      'redirect_uri' => $redirectUri,
      'code_verifier' => $codeVerifier,
    ]);

    $tokenData = json_decode($tokenResponse, TRUE);
    $accessToken = $tokenData['access_token'];

    $toolResponse = $this->drupalPost(
      '/api/ai-connect/v1/tools/drupal.getCurrentUser',
      json_encode([]),
      [
        'headers' => [
          'Authorization' => 'Bearer ' . $accessToken,
          'Content-Type' => 'application/json',
        ],
      ]
    );

    $toolData = json_decode($toolResponse, TRUE);
    $this->assertTrue($toolData['success']);
    $this->assertEquals($this->testUser->id(), $toolData['data']['user_id']);
  }

  /**
   * Generates PKCE code challenge from verifier.
   *
   * @param string $verifier
   *   The code verifier string.
   *
   * @return string
   *   The base64url-encoded SHA256 hash.
   */
  protected function generateCodeChallenge($verifier) {
    return rtrim(strtr(base64_encode(hash('sha256', $verifier, TRUE)), '+/', '-_'), '=');
  }

  /**
   * Performs POST request to Drupal.
   *
   * @param string $path
   *   The path to post to.
   * @param mixed $data
   *   The data to post.
   * @param array $options
   *   Additional request options.
   *
   * @return string
   *   The response body.
   */
  protected function drupalPost($path, $data, $options = []) {
    $client = $this->getHttpClient();

    $url = $this->buildUrl($path);

    $headers = $options['headers'] ?? [];
    $headers['Content-Type'] = $headers['Content-Type'] ?? 'application/x-www-form-urlencoded';

    $body = is_array($data) ? http_build_query($data) : $data;

    $response = $client->post($url, [
      'headers' => $headers,
      'body' => $body,
      'http_errors' => FALSE,
    ]);

    return $response->getBody()->getContents();
  }

}
