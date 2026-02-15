<?php

namespace Drupal\ai_connect\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;

class RateLimiterService {

  protected $configFactory;
  protected $database;

  public function __construct(ConfigFactoryInterface $config_factory, Connection $database) {
    $this->configFactory = $config_factory;
    $this->database = $database;
  }

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

  public function recordRequest($identifier) {
    $this->incrementWindow($identifier, 'minute', 60);
    $this->incrementWindow($identifier, 'hour', 3600);
  }

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
