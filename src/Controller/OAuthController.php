<?php

namespace Drupal\ai_connect\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\ai_connect\Service\OAuthService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Handles OAuth 2.0 authorization and token endpoints.
 *
 * Implements the OAuth 2.0 authorization code flow with PKCE support,
 * including authorization, token exchange, and token revocation endpoints.
 */
class OAuthController extends ControllerBase {

  /**
   * The OAuth service.
   *
   * @var \Drupal\ai_connect\Service\OAuthService
   */
  protected $oauthService;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The URL generator.
   *
   * @var \Symfony\Component\Routing\Generator\UrlGeneratorInterface
   */
  protected $urlGenerator;

  /**
   * Constructs an OAuthController object.
   *
   * @param \Drupal\ai_connect\Service\OAuthService $oauth_service
   *   The OAuth service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Symfony\Component\Routing\Generator\UrlGeneratorInterface $url_generator
   *   The URL generator.
   */
  public function __construct(OAuthService $oauth_service, AccountProxyInterface $current_user, RequestStack $request_stack, UrlGeneratorInterface $url_generator) {
    $this->oauthService = $oauth_service;
    $this->currentUser = $current_user;
    $this->requestStack = $request_stack;
    $this->urlGenerator = $url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ai_connect.oauth_service'),
      $container->get('current_user'),
      $container->get('request_stack'),
      $container->get('url_generator')
    );
  }

  /**
   * Handles the OAuth 2.0 authorization endpoint.
   *
   * Validates the authorization request, displays a consent screen, and
   * generates an authorization code upon user approval.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request object.
   *
   * @return array|\Symfony\Component\HttpFoundation\Response
   *   A render array for the consent screen or an error response.
   */
  public function authorize(Request $request) {
    $client_id = $request->query->get('client_id');
    $redirect_uri = $request->query->get('redirect_uri');
    $response_type = $request->query->get('response_type');
    $scope = $request->query->get('scope');
    $state = $request->query->get('state');
    $code_challenge = $request->query->get('code_challenge');
    $code_challenge_method = $request->query->get('code_challenge_method');

    if ($response_type !== 'code') {
      return $this->sendError('unsupported_response_type', 'Only authorization code flow is supported');
    }

    // Auto-register client if it doesn't exist (like WordPress).
    $this->oauthService->autoRegisterClient($client_id, $redirect_uri, $scope);

    if (!$this->oauthService->validateClient($client_id)) {
      return $this->sendError('invalid_client', 'Invalid client_id');
    }

    if (!$this->oauthService->validateRedirectUri($client_id, $redirect_uri)) {
      return $this->sendError('invalid_request', 'Invalid redirect_uri');
    }

    if (empty($code_challenge) || $code_challenge_method !== 'S256') {
      return $this->sendError('invalid_request', 'PKCE required: code_challenge with S256 method');
    }

    $scopes = !empty($scope) ? explode(' ', $scope) : ['read'];

    if (!$this->oauthService->validateScopes($client_id, $scopes)) {
      return $this->sendError('invalid_scope', 'Invalid scope requested');
    }

    if ($request->request->get('ai_connect_oauth_approve')) {
      return $this->handleApproval($request, $client_id, $redirect_uri, $code_challenge, $code_challenge_method, $scopes, $state);
    }

    if ($request->request->get('ai_connect_oauth_deny')) {
      return $this->handleDenial($redirect_uri, $state);
    }

    return $this->showConsentScreen($request, $client_id, $redirect_uri, $scope, $state, $code_challenge, $code_challenge_method, $scopes);
  }

  /**
   * Handles user approval of the authorization request.
   *
   * Creates an authorization code and redirects to the client's redirect URI
   * with the code and state parameter.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request object.
   * @param string $client_id
   *   The OAuth client ID.
   * @param string $redirect_uri
   *   The client's redirect URI.
   * @param string $code_challenge
   *   The PKCE code challenge.
   * @param string $code_challenge_method
   *   The PKCE code challenge method (S256).
   * @param array $scopes
   *   The requested scopes.
   * @param string $state
   *   The state parameter for CSRF protection.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A redirect response or error response.
   */
  protected function handleApproval(Request $request, $client_id, $redirect_uri, $code_challenge, $code_challenge_method, array $scopes, $state) {
    $current_user = $this->currentUser;

    if (!$current_user->isAuthenticated()) {
      $destination = $this->requestStack->getCurrentRequest()->getRequestUri();
      return new RedirectResponse($this->urlGenerator->generateFromRoute('user.login', [], ['query' => ['destination' => $destination]]));
    }

    $user_id = $current_user->id();

    $code = $this->oauthService->createAuthorizationCode(
          $client_id,
          $user_id,
          $redirect_uri,
          $code_challenge,
          $code_challenge_method,
          $scopes
      );

    if (!$code) {
      return $this->sendError('server_error', 'Failed to create authorization code');
    }

    if ($redirect_uri === 'urn:ietf:wg:oauth:2.0:oob') {
      return $this->showOobCode($code);
    }

    $redirect_url = $redirect_uri . (strpos($redirect_uri, '?') !== FALSE ? '&' : '?') .
        'code=' . urlencode($code) . '&state=' . urlencode($state);

    return new RedirectResponse($redirect_url);
  }

  /**
   * Handles user denial of the authorization request.
   *
   * Redirects to the client's redirect URI with an access_denied error.
   *
   * @param string $redirect_uri
   *   The client's redirect URI.
   * @param string $state
   *   The state parameter for CSRF protection.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A redirect response or error response.
   */
  protected function handleDenial($redirect_uri, $state) {
    if ($redirect_uri === 'urn:ietf:wg:oauth:2.0:oob') {
      return new Response('Authorization denied', 403);
    }

    $redirect_url = $redirect_uri . (strpos($redirect_uri, '?') !== FALSE ? '&' : '?') .
        'error=access_denied&error_description=' . urlencode('User denied authorization') . '&state=' . urlencode($state);

    return new RedirectResponse($redirect_url);
  }

  /**
   * Displays the OAuth consent screen to the user.
   *
   * Shows the client information and requested scopes for user approval.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request object.
   * @param string $client_id
   *   The OAuth client ID.
   * @param string $redirect_uri
   *   The client's redirect URI.
   * @param string $scope
   *   The requested scopes as a space-separated string.
   * @param string $state
   *   The state parameter for CSRF protection.
   * @param string $code_challenge
   *   The PKCE code challenge.
   * @param string $code_challenge_method
   *   The PKCE code challenge method (S256).
   * @param array $scopes
   *   The requested scopes as an array.
   *
   * @return array|\Symfony\Component\HttpFoundation\Response
   *   A render array for the consent screen or a redirect response.
   */
  protected function showConsentScreen(Request $request, $client_id, $redirect_uri, $scope, $state, $code_challenge, $code_challenge_method, array $scopes) {
    $current_user = $this->currentUser;

    if (!$current_user->isAuthenticated()) {
      $destination = $this->requestStack->getCurrentRequest()->getRequestUri();
      return new RedirectResponse($this->urlGenerator->generateFromRoute('user.login', [], ['query' => ['destination' => $destination]]));
    }

    $client = $this->oauthService->getClient($client_id);

    $build = [
      '#theme' => 'ai_connect_oauth_consent',
      '#client' => $client,
      '#scopes' => $scopes,
      '#request_uri' => $request->getRequestUri(),
      '#user' => $current_user,
    ];

    return $build;
  }

  /**
   * Displays the out-of-band authorization code.
   *
   * Used for clients that cannot handle HTTP redirects (e.g., CLI tools).
   *
   * @param string $code
   *   The authorization code.
   *
   * @return array
   *   A render array displaying the authorization code.
   */
  protected function showOobCode($code) {
    $build = [
      '#theme' => 'ai_connect_oauth_oob',
      '#code' => $code,
    ];

    return $build;
  }

  /**
   * Handles the OAuth 2.0 token endpoint.
   *
   * Exchanges authorization codes for access tokens or refreshes existing
   * tokens using the refresh token grant.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing the token or an error.
   */
  public function token(Request $request) {
    $grant_type = $request->request->get('grant_type');
    $client_id = $request->request->get('client_id');

    if (!$this->oauthService->validateClient($client_id)) {
      return new JsonResponse(['error' => 'invalid_client', 'error_description' => 'Invalid client_id'], 400);
    }

    if ($grant_type === 'authorization_code') {
      $code = $request->request->get('code');
      $code_verifier = $request->request->get('code_verifier');
      $redirect_uri = $request->request->get('redirect_uri');

      $result = $this->oauthService->exchangeCodeForToken($code, $client_id, $code_verifier, $redirect_uri);

      if (isset($result['error'])) {
        return new JsonResponse($result, 400);
      }

      return new JsonResponse($result);
    }
    elseif ($grant_type === 'refresh_token') {
      $refresh_token = $request->request->get('refresh_token');

      $result = $this->oauthService->exchangeRefreshToken($refresh_token, $client_id);

      if (isset($result['error'])) {
        return new JsonResponse($result, 400);
      }

      return new JsonResponse($result);
    }

    return new JsonResponse(['error' => 'unsupported_grant_type', 'error_description' => 'Unsupported grant type'], 400);
  }

  /**
   * Handles the OAuth 2.0 token revocation endpoint.
   *
   * Revokes an access or refresh token, invalidating it for future use.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response indicating success or failure.
   */
  public function revoke(Request $request) {
    $token = $request->request->get('token');

    if (empty($token)) {
      return new JsonResponse(['error' => 'invalid_request', 'error_description' => 'Token is required'], 400);
    }

    $result = $this->oauthService->revokeToken($token);

    if ($result) {
      return new JsonResponse(['success' => TRUE]);
    }

    return new JsonResponse(['error' => 'invalid_token', 'error_description' => 'Failed to revoke token'], 400);
  }

  /**
   * Sends an error response.
   *
   * @param string $error
   *   The error code.
   * @param string $description
   *   The error description.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   An HTTP response with the error message.
   */
  protected function sendError($error, $description) {
    return new Response($description, 400);
  }

}
