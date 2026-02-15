<?php

namespace Drupal\ai_connect\Service;

use Symfony\Component\HttpFoundation\RequestStack;

class ManifestService {

  protected $tools = [];
  protected $requestStack;

  public function __construct(RequestStack $request_stack) {
    $this->requestStack = $request_stack;
  }

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

  public function getTools() {
    return array_values($this->tools);
  }

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
        'type' => 'bearer',
        'login_url' => $baseUrl . '/api/ai-connect/auth/login',
        'description' => 'Direct authentication with Drupal username and password',
        'method' => 'POST',
        'body' => [
          'username' => 'Drupal username',
          'password' => 'Drupal password',
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

  public function generateJson($pretty = TRUE) {
    $manifest = $this->generate();
    $options = $pretty ? JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES : JSON_UNESCAPED_SLASHES;
    return json_encode($manifest, $options);
  }

}
