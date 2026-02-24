<?php

namespace Drupal\ai_connect\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\ai_connect\Service\ModuleManager;
use Drupal\ai_connect\Service\AuthService;
use Drupal\ai_connect\Service\OAuthService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller for executing AI Connect tools.
 */
class ToolsController extends ControllerBase {

  /**
   * The module manager service.
   *
   * @var \Drupal\ai_connect\Service\ModuleManager
   */
  protected $moduleManager;

  /**
   * The auth service.
   *
   * @var \Drupal\ai_connect\Service\AuthService
   */
  protected $authService;

  /**
   * The OAuth service.
   *
   * @var \Drupal\ai_connect\Service\OAuthService
   */
  protected $oauthService;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The account switcher service.
   *
   * @var \Drupal\Core\Session\AccountSwitcherInterface
   */
  protected $accountSwitcher;

  /**
   * Constructs a ToolsController object.
   *
   * @param \Drupal\ai_connect\Service\ModuleManager $module_manager
   *   The module manager service.
   * @param \Drupal\ai_connect\Service\AuthService $auth_service
   *   The auth service.
   * @param \Drupal\ai_connect\Service\OAuthService $oauth_service
   *   The OAuth service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountSwitcherInterface $account_switcher
   *   The account switcher service.
   */
  public function __construct(ModuleManager $module_manager, AuthService $auth_service, OAuthService $oauth_service, EntityTypeManagerInterface $entity_type_manager, AccountSwitcherInterface $account_switcher) {
    $this->moduleManager = $module_manager;
    $this->authService = $auth_service;
    $this->oauthService = $oauth_service;
    $this->entityTypeManager = $entity_type_manager;
    $this->accountSwitcher = $account_switcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
          $container->get('ai_connect.module_manager'),
          $container->get('ai_connect.auth'),
          $container->get('ai_connect.oauth_service'),
          $container->get('entity_type.manager'),
          $container->get('account_switcher')
      );
  }

  /**
   * Executes a specific tool.
   *
   * @param string $tool_name
   *   The name of the tool to execute.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function execute($tool_name, Request $request) {
    $authHeader = $request->headers->get('Authorization', '');

    if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
      return new JsonResponse(
            [
              'error' => 'Authorization header with Bearer token is required',
            ], 401
        );
    }

    $token = $matches[1];

    $tokenData = $this->oauthService->validateToken($token);

    if (isset($tokenData['error'])) {
      return new JsonResponse(['error' => $tokenData['error_description'] ?? 'Invalid or expired token'], 401);
    }

    $account = $this->entityTypeManager->getStorage('user')->load($tokenData['user_id']);
    if ($account) {
      $this->accountSwitcher->switchTo($account);
    }

    $data = json_decode($request->getContent(), TRUE);

    if (empty($tool_name)) {
      return new JsonResponse(
            [
              'error' => 'Tool name is required',
            ], 400
        );
    }

    $result = $this->moduleManager->executeTool($tool_name, $data ?? []);

    if (isset($result['success']) && $result['success'] === FALSE) {
      $code = 400;
      if (isset($result['error']['code'])) {
        if ($result['error']['code'] === 'module_not_found' || $result['error']['code'] === 'tool_not_found') {
          $code = 404;
        }
      }

      return new JsonResponse(
            [
              'error' => $result['error']['message'] ?? 'Tool execution failed',
            ], $code
        );
    }

    return new JsonResponse($result);
  }

}
