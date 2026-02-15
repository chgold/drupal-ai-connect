<?php

namespace Drupal\ai_connect\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\ai_connect\Service\ModuleManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class ToolsController extends ControllerBase {

  protected $moduleManager;

  public function __construct(ModuleManager $module_manager) {
    $this->moduleManager = $module_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ai_connect.module_manager')
    );
  }

  public function execute($tool_name, Request $request) {
    $data = json_decode($request->getContent(), TRUE);

    if (empty($tool_name)) {
      return new JsonResponse([
        'error' => 'Tool name is required',
      ], 400);
    }

    $result = $this->moduleManager->executeTool($tool_name, $data ?? []);

    if (isset($result['success']) && $result['success'] === FALSE) {
      $code = 400;
      if (isset($result['error']['code'])) {
        if ($result['error']['code'] === 'module_not_found' || $result['error']['code'] === 'tool_not_found') {
          $code = 404;
        }
      }

      return new JsonResponse([
        'error' => $result['error']['message'] ?? 'Tool execution failed',
      ], $code);
    }

    return new JsonResponse($result);
  }

}
