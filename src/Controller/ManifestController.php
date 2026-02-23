<?php

namespace Drupal\ai_connect\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\ai_connect\Service\ManifestService;
use Drupal\ai_connect\Module\CoreModule;
use Drupal\ai_connect\Module\TranslationModule;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller for AI Connect manifest generation.
 */
class ManifestController extends ControllerBase {

  /**
   * The manifest service.
   *
   * @var \Drupal\ai_connect\Service\ManifestService
   */
  protected $manifestService;

  /**
   * Constructs a ManifestController object.
   *
   * @param \Drupal\ai_connect\Service\ManifestService $manifest_service
   *   The manifest service.
   */
  public function __construct(ManifestService $manifest_service) {
    $this->manifestService = $manifest_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
          $container->get('ai_connect.manifest')
      );
  }

  /**
   * Returns the AI Connect manifest.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response with manifest data.
   */
  public function get() {
    new CoreModule($this->manifestService);
    new TranslationModule($this->manifestService);

    $manifest = $this->manifestService->generate();

    return new JsonResponse($manifest);
  }

}
