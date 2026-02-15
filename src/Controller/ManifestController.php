<?php

namespace Drupal\ai_connect\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\ai_connect\Service\ManifestService;
use Drupal\ai_connect\Module\CoreModule;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class ManifestController extends ControllerBase {

  protected $manifestService;

  public function __construct(ManifestService $manifest_service) {
    $this->manifestService = $manifest_service;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ai_connect.manifest')
    );
  }

  public function get() {
    $coreModule = new CoreModule($this->manifestService);

    $manifest = $this->manifestService->generate();

    return new JsonResponse($manifest);
  }

}
