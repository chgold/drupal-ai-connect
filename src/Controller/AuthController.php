<?php

namespace Drupal\ai_connect\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\ai_connect\Service\AuthService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller for AI Connect authentication.
 */
class AuthController extends ControllerBase
{

    /**
     * The auth service.
     *
     * @var \Drupal\ai_connect\Service\AuthService
     */
    protected $authService;

    /**
     * Constructs an AuthController object.
     *
     * @param \Drupal\ai_connect\Service\AuthService $auth_service
     *   The auth service.
     */
    public function __construct(AuthService $auth_service)
    {
        $this->authService = $auth_service;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('ai_connect.auth')
        );
    }

    /**
     * Handles user login and returns JWT token.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The request object.
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     *   The JSON response with access token.
     */
    public function login(Request $request)
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['username']) || empty($data['password'])) {
            return new JsonResponse(
                [
                'error' => 'Username and password are required',
                ], 400
            );
        }

        $result = $this->authService->authenticateUser(
            $data['username'],
            $data['password']
        );

        if (!$result['success']) {
            return new JsonResponse(
                [
                'error' => $result['error'] ?? 'Authentication failed',
                ], 401
            );
        }

        return new JsonResponse($result);
    }

    /**
     * Handles token refresh (not implemented).
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The request object.
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     *   The JSON response.
     */
    public function refresh(Request $request)
    {
        return new JsonResponse(
            [
            'error' => 'Refresh not implemented - tokens are long-lived',
            ], 501
        );
    }

}
