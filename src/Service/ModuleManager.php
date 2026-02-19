<?php

namespace Drupal\ai_connect\Service;

use Drupal\ai_connect\Module\CoreModule;
use Drupal\ai_connect\Module\TranslationModule;

/**
 * Service for managing AI Connect modules.
 */
class ModuleManager {

  /**
   * Registered modules array.
   *
   * @var array
   */
  protected $modules = [];

  /**
   * The manifest service.
   *
   * @var \Drupal\ai_connect\Service\ManifestService
   */
  protected $manifestService;

  /**
   * Constructs a ModuleManager object.
   *
   * @param \Drupal\ai_connect\Service\ManifestService $manifest_service
   *   The manifest service.
   */
  public function __construct(ManifestService $manifest_service) {
    $this->manifestService = $manifest_service;
    $this->registerModule(new CoreModule($manifest_service));
    $this->registerModule(new TranslationModule($manifest_service));
  }

  /**
   * Registers a module.
   *
   * @param object $moduleInstance
   *   The module instance to register.
   *
   * @return bool
   *   TRUE if registered successfully.
   */
  public function registerModule($moduleInstance) {
    $moduleName = $moduleInstance->getModuleName();
    $this->modules[$moduleName] = $moduleInstance;
    return TRUE;
  }

  /**
   * Gets a module by name.
   *
   * @param string $moduleName
   *   The module name.
   *
   * @return object|null
   *   The module instance or NULL if not found.
   */
  public function getModule($moduleName) {
    return $this->modules[$moduleName] ?? NULL;
  }

  /**
   * Executes a tool.
   *
   * @param string $toolName
   *   The tool name in format 'module.tool'.
   * @param array $params
   *   The tool parameters.
   *
   * @return array
   *   The tool execution result.
   */
  public function executeTool($toolName, array $params = []) {
    if (strpos($toolName, '.') === FALSE) {
      return $this->error('invalid_tool_name', 'Tool name must be in format module.tool');
    }

    [$moduleName, $tool] = explode('.', $toolName, 2);

    $module = $this->getModule($moduleName);
    if (!$module) {
      return $this->error('module_not_found', sprintf('Module %s not found', $moduleName));
    }

    return $module->executeTool($tool, $params);
  }

  /**
   * Gets all tools from all modules.
   *
   * @return array
   *   Array of all registered tools.
   */
  public function getAllTools() {
    $allTools = [];
    foreach ($this->modules as $module) {
      $allTools = array_merge($allTools, $module->getTools());
    }
    return $allTools;
  }

  /**
   * Creates an error response.
   *
   * @param string $code
   *   The error code.
   * @param string $message
   *   The error message.
   * @param mixed $data
   *   Optional error data.
   *
   * @return array
   *   The error response array.
   */
  protected function error($code, $message, $data = NULL) {
    return [
      'success' => FALSE,
      'error' => [
        'code' => $code,
        'message' => $message,
        'data' => $data,
      ],
    ];
  }

}
