<?php

namespace Drupal\ai_connect\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;

/**
 * Service for rate limiting API requests.
 */
class RateLimiterService {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a RateLimiterService object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(ConfigFactoryInterface $config_factory, Connection $database) {
    $this->configFactory = $config_factory;
    $this->database = $database;
  }

  /**
   * Checks if a request is rate limited.
   *
   * @param string $identifier
   *   The identifier to check (usually user ID or IP).
   *
   * @return array
   *   Array with 'limited' boolean and optional retry info.
   */
  public function isRateLimited($identifier) {
    $config = $this->configFactory->get('ai_connect.settings');
    $perMinute = $config->get('rate_limit_per_minute') ?? 50;
    $perHour = $config->get('rate_limit_per_hour') ?? 1000;

    $minuteCheck = $this->checkWindow($identifier, 'minute', 60, $perMinute);
    if ($minuteCheck['limited']) {
      return $minuteCheck;
    }

    $hourCheck = $this->checkWindow($identifier, 'hour', 3600, $perHour);
    if ($hourCheck['limited']) {
      return $hourCheck;
    }

    return ['limited' => FALSE];
  }

  /**
   * Records a request for rate limiting tracking.
   *
   * @param string $identifier
   *   The identifier to record (usually user ID or IP).
   */
  public function recordRequest($identifier) {
    $this->incrementWindow($identifier, 'minute', 60);
    $this->incrementWindow($identifier, 'hour', 3600);
  }

  /**
   * Checks if a specific window is rate limited.
   *
   * @param string $identifier
   *   The identifier to check.
   * @param string $windowType
   *   The type of window (minute/hour).
   * @param int $windowSize
   *   The size of the window in seconds.
   * @param int $limit
   *   The request limit for this window.
   *
   * @return array
   *   Array with rate limit status.
   */
  protected function checkWindow($identifier, $windowType, $windowSize, $limit) {
    $now = time();
    $windowStart = floor($now / $windowSize) * $windowSize;

    $record = $this->database->select('ai_connect_rate_limits', 'r')
      ->fields('r')
      ->condition('identifier', $identifier)
      ->condition('window_type', $windowType)
      ->condition('window_start', $windowStart)
      ->execute()
      ->fetchAssoc();

    if (!$record) {
      return ['limited' => FALSE];
    }

    if ($record['request_count'] >= $limit) {
      $retryAfter = $windowStart + $windowSize - $now;
      return [
        'limited' => TRUE,
        'reason' => sprintf('%d requests per %s', $limit, $windowType),
        'retry_after' => $retryAfter,
        'limit' => $limit,
        'current' => $record['request_count'],
      ];
    }

    return ['limited' => FALSE];
  }

  /**
   * Increments the request count for a specific window.
   *
   * @param string $identifier
   *   The identifier to increment.
   * @param string $windowType
   *   The type of window (minute/hour).
   * @param int $windowSize
   *   The size of the window in seconds.
   */
  protected function incrementWindow($identifier, $windowType, $windowSize) {
    $now = time();
    $windowStart = floor($now / $windowSize) * $windowSize;

    $this->database->merge('ai_connect_rate_limits')
      ->keys([
        'identifier' => $identifier,
        'window_type' => $windowType,
        'window_start' => $windowStart,
      ])
      ->fields([
        'request_count' => 1,
        'last_request' => $now,
      ])
      ->expression('request_count', 'request_count + 1')
      ->execute();

    $this->database->delete('ai_connect_rate_limits')
      ->condition('window_start', $now - 86400, '<')
      ->execute();
  }

}
