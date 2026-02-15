<?php

namespace Drupal\ai_connect\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\ai_connect\Service\AuthService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class AuthController extends ControllerBase {

  protected $authService;

  public function __construct(AuthService $auth_service) {
    $this->authService = $auth_service;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ai_connect.auth')
    );
  }

  public function login(Request $request) {
    $data = json_decode($request->getContent(), TRUE);

    if (empty($data['username']) || empty($data['password'])) {
      return new JsonResponse([
        'error' => 'Username and password are required',
      ], 400);
    }

    $result = $this->authService->authenticateUser(
      $data['username'],
      $data['password']
    );

    if (!$result['success']) {
      return new JsonResponse([
        'error' => $result['error'] ?? 'Authentication failed',
      ], 401);
    }

    return new JsonResponse($result);
  }

  public function refresh(Request $request) {
    return new JsonResponse([
      'error' => 'Refresh not implemented - tokens are long-lived',
    ], 501);
  }

}
