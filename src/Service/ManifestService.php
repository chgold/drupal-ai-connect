<?php

namespace Drupal\ai_connect\Service;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service for managing and generating AI Connect manifest.
 */
class ManifestService {

  /**
   * Registered tools array.
   *
   * @var array
   */
  protected $tools = [];

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a ManifestService object.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack service.
   */
  public function __construct(RequestStack $request_stack) {
    $this->requestStack = $request_stack;
  }

  /**
   * Registers a tool in the manifest.
   *
   * @param string $name
   *   The tool name.
   * @param array $config
   *   The tool configuration.
   *
   * @return bool
   *   TRUE if registered successfully, FALSE otherwise.
   */
  public function registerTool($name, array $config) {
    if (empty($name) || empty($config['description']) || empty($config['input_schema'])) {
      return FALSE;
    }

    $this->tools[$name] = [
      'name' => $name,
      'description' => $config['description'],
      'input_schema' => $config['input_schema'],
    ];

    return TRUE;
  }

  /**
   * Gets all registered tools.
   *
   * @return array
   *   Array of registered tools.
   */
  public function getTools() {
    return array_values($this->tools);
  }

  /**
   * Generates the complete manifest array.
   *
   * @return array
   *   The manifest data.
   */
  public function generate() {
    $request = $this->requestStack->getCurrentRequest();
    $baseUrl = $request->getSchemeAndHttpHost() . $request->getBasePath();

    $manifest = [
      'schema_version' => '1.0',
      'name' => 'drupal-ai-connect',
      'version' => '1.0.0',
      'description' => 'WebMCP bridge for Drupal - manage content and users',
      'api_version' => 'v1',
      'capabilities' => [
        'tools' => TRUE,
        'resources' => FALSE,
        'prompts' => FALSE,
      ],
      'server' => [
        'url' => $baseUrl . '/api/ai-connect/v1',
        'description' => 'Drupal AI Connect API',
      ],
      'auth' => [
        'type' => 'oauth2',
        'flow' => 'authorization_code',
        'authorization_url' => $baseUrl . '/oauth/authorize',
        'token_url' => $baseUrl . '/api/ai-connect/v1/oauth/token',
        'revoke_url' => $baseUrl . '/api/ai-connect/v1/oauth/revoke',
        'pkce_required' => TRUE,
        'code_challenge_method' => 'S256',
        'redirect_uri' => 'urn:ietf:wg:oauth:2.0:oob',
        'scopes' => [
          'read' => 'Read content and settings',
          'write' => 'Create and modify content',
          'delete' => 'Delete content',
        ],
      ],
      'usage' => [
        'tools_endpoint' => $baseUrl . '/api/ai-connect/v1/tools/{tool_name}',
        'method' => 'POST',
        'headers' => [
          'Authorization' => 'Bearer {access_token}',
          'Content-Type' => 'application/json',
        ],
      ],
    ];

    if (!empty($this->tools)) {
      $manifest['tools'] = $this->getTools();
    }

    return $manifest;
  }

  /**
   * Generates the manifest as JSON string.
   *
   * @param bool $pretty
   *   Whether to pretty-print the JSON.
   *
   * @return string
   *   The JSON-encoded manifest.
   */
  public function generateJson($pretty = TRUE) {
    $manifest = $this->generate();
    $options = $pretty ? JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES : JSON_UNESCAPED_SLASHES;
    return json_encode($manifest, $options);
  }

}
