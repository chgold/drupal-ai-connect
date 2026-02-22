<?php

namespace Drupal\ai_connect\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\ai_connect\Service\OAuthService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;

class OAuthController extends ControllerBase {

  protected $oauthService;

  public function __construct(OAuthService $oauth_service) {
    $this->oauthService = $oauth_service;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ai_connect.oauth_service')
    );
  }

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

  protected function handleApproval(Request $request, $client_id, $redirect_uri, $code_challenge, $code_challenge_method, array $scopes, $state) {
    $current_user = \Drupal::currentUser();

    if (!$current_user->isAuthenticated()) {
      $destination = \Drupal::request()->getRequestUri();
      return new RedirectResponse(\Drupal::url('user.login', [], ['query' => ['destination' => $destination]]));
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

  protected function handleDenial($redirect_uri, $state) {
    if ($redirect_uri === 'urn:ietf:wg:oauth:2.0:oob') {
      return new Response('Authorization denied', 403);
    }

    $redirect_url = $redirect_uri . (strpos($redirect_uri, '?') !== FALSE ? '&' : '?') .
      'error=access_denied&error_description=' . urlencode('User denied authorization') . '&state=' . urlencode($state);

    return new RedirectResponse($redirect_url);
  }

  protected function showConsentScreen(Request $request, $client_id, $redirect_uri, $scope, $state, $code_challenge, $code_challenge_method, array $scopes) {
    $current_user = \Drupal::currentUser();

    if (!$current_user->isAuthenticated()) {
      $destination = \Drupal::request()->getRequestUri();
      return new RedirectResponse(\Drupal::url('user.login', [], ['query' => ['destination' => $destination]]));
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

  protected function showOobCode($code) {
    $build = [
      '#theme' => 'ai_connect_oauth_oob',
      '#code' => $code,
    ];

    return $build;
  }

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

  protected function sendError($error, $description) {
    return new Response($description, 400);
  }

}
