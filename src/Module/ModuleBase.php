<?php

namespace Drupal\ai_connect\Module;

/**
 * Base class for AI Connect modules.
 */
abstract class ModuleBase {

  /**
   * The module name.
   *
   * @var string
   */
  protected $moduleName;

  /**
   * Registered tools.
   *
   * @var array
   */
  protected $tools = [];

  /**
   * The manifest service.
   *
   * @var \Drupal\ai_connect\Service\ManifestService
   */
  protected $manifestService;

  /**
   * Constructs a ModuleBase object.
   *
   * @param \Drupal\ai_connect\Service\ManifestService $manifestService
   *   The manifest service.
   */
  public function __construct($manifestService) {
    $this->manifestService = $manifestService;
    $this->registerTools();
  }

  /**
   * Registers all tools provided by this module.
   */
  abstract protected function registerTools();

  /**
   * Registers a single tool.
   *
   * @param string $name
   *   The tool name.
   * @param array $config
   *   The tool configuration.
   *
   * @return bool
   *   TRUE if registered successfully.
   */
  protected function registerTool($name, array $config) {
    if (!isset($config['description']) || !isset($config['input_schema'])) {
      return FALSE;
    }

    $fullName = $this->moduleName . '.' . $name;

    $this->tools[$name] = [
      'name' => $fullName,
      'description' => $config['description'],
      'input_schema' => $config['input_schema'],
      'callback' => $config['callback'] ?? [$this, 'execute' . ucfirst($name)],
    ];

    if ($this->manifestService) {
      $this->manifestService->registerTool($fullName, [
        'description' => $config['description'],
        'input_schema' => $config['input_schema'],
      ]);
    }

    return TRUE;
  }

  /**
   * Executes a tool.
   *
   * @param string $toolName
   *   The tool name.
   * @param array $params
   *   The tool parameters.
   *
   * @return array
   *   The execution result.
   */
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

  /**
   * Validates parameters against a schema.
   *
   * @param array $params
   *   The parameters to validate.
   * @param array $schema
   *   The validation schema.
   *
   * @return array
   *   Validated parameters or error array.
   */
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

  /**
   * Validates a value against a type.
   *
   * @param mixed $value
   *   The value to validate.
   * @param string $type
   *   The expected type.
   *
   * @return bool
   *   TRUE if valid.
   */
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

  /**
   * Gets all tools.
   *
   * @return array
   *   Array of tools.
   */
  public function getTools() {
    return $this->tools;
  }

  /**
   * Gets the module name.
   *
   * @return string
   *   The module name.
   */
  public function getModuleName() {
    return $this->moduleName;
  }

  /**
   * Creates a success response.
   *
   * @param mixed $data
   *   The response data.
   * @param string|null $message
   *   Optional success message.
   *
   * @return array
   *   Success response array.
   */
  protected function success($data, $message = NULL) {
    return [
      'success' => TRUE,
      'data' => $data,
      'message' => $message,
    ];
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
   *   Error response array.
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
