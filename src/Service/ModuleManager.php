<?php

namespace Drupal\ai_connect\Service;

use Drupal\ai_connect\Service\ManifestService;
use Drupal\ai_connect\Module\CoreModule;

class ModuleManager {

  protected $modules = [];
  protected $manifestService;

  public function __construct(ManifestService $manifest_service) {
    $this->manifestService = $manifest_service;
    // Auto-register the core Drupal module.
    $this->registerModule(new CoreModule($manifest_service));
  }

  public function registerModule($moduleInstance) {
    $moduleName = $moduleInstance->getModuleName();
    $this->modules[$moduleName] = $moduleInstance;
    return TRUE;
  }

  public function getModule($moduleName) {
    return $this->modules[$moduleName] ?? NULL;
  }

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

  public function getAllTools() {
    $allTools = [];
    foreach ($this->modules as $module) {
      $allTools = array_merge($allTools, $module->getTools());
    }
    return $allTools;
  }

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
