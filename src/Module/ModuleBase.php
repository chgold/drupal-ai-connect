<?php

namespace Drupal\ai_connect\Module;

abstract class ModuleBase {

  protected $moduleName;
  protected $tools = [];
  protected $manifestService;

  public function __construct($manifestService) {
    $this->manifestService = $manifestService;
    $this->registerTools();
  }

  abstract protected function registerTools();

  protected function registerTool($name, array $config) {
    if (!isset($config['description']) || !isset($config['input_schema'])) {
      return FALSE;
    }

    $fullName = $this->moduleName . '.' . $name;

    $this->tools[$name] = [
      'name' => $fullName,
      'description' => $config['description'],
      'input_schema' => $config['input_schema'],
      'callback' => $config['callback'] ?? [$this, 'execute_' . $name],
    ];

    if ($this->manifestService) {
      $this->manifestService->registerTool($fullName, [
        'description' => $config['description'],
        'input_schema' => $config['input_schema'],
      ]);
    }

    return TRUE;
  }

  public function executeTool($toolName, array $params = []) {
    if (!isset($this->tools[$toolName])) {
      return $this->error('tool_not_found', sprintf('Tool %s not found', $toolName));
    }

    $tool = $this->tools[$toolName];

    $validated = $this->validateParams($params, $tool['input_schema']);
    if (isset($validated['error'])) {
      return $validated;
    }

    if (!is_callable($tool['callback'])) {
      return $this->error('tool_not_callable', sprintf('Tool %s is not callable', $toolName));
    }

    try {
      return call_user_func($tool['callback'], $validated);
    }
    catch (\Exception $e) {
      return $this->error('tool_execution_error', $e->getMessage());
    }
  }

  protected function validateParams($params, $schema) {
    if (!isset($schema['properties'])) {
      return $params;
    }

    $validated = [];
    $required = $schema['required'] ?? [];

    foreach ($schema['properties'] as $key => $prop) {
      $isRequired = in_array($key, $required);

      if ($isRequired && !isset($params[$key])) {
        return $this->error('missing_parameter', sprintf('Required parameter %s is missing', $key));
      }

      if (isset($params[$key])) {
        $value = $params[$key];

        if (isset($prop['type'])) {
          $typeValid = $this->validateType($value, $prop['type']);
          if (!$typeValid) {
            return $this->error(
              'invalid_type',
              sprintf('Parameter %s must be of type %s', $key, $prop['type'])
            );
          }
        }

        $validated[$key] = $value;
      }
      elseif (isset($prop['default'])) {
        $validated[$key] = $prop['default'];
      }
    }

    return $validated;
  }

  protected function validateType($value, $type) {
    switch ($type) {
      case 'string':
        return is_string($value);

      case 'integer':
        return is_int($value) || (is_string($value) && ctype_digit($value));

      case 'number':
        return is_numeric($value);

      case 'boolean':
        return is_bool($value) || in_array($value, ['true', 'false', 0, 1], TRUE);

      case 'array':
        return is_array($value);

      case 'object':
        return is_object($value) || is_array($value);

      default:
        return TRUE;
    }
  }

  public function getTools() {
    return $this->tools;
  }

  public function getModuleName() {
    return $this->moduleName;
  }

  protected function success($data, $message = NULL) {
    return [
      'success' => TRUE,
      'data' => $data,
      'message' => $message,
    ];
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
