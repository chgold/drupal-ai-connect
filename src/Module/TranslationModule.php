<?php

namespace Drupal\ai_connect\Module;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Translation module for AI Connect.
 */
class TranslationModule extends ModuleBase {

  /**
   * Module name.
   *
   * @var string
   */
  protected $moduleName = 'translation';

  /**
   * MyMemory API endpoint.
   */
  private const MYMEMORY_API = 'https://api.mymemory.translated.net/get';

  /**
   * HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Constructs a TranslationModule object.
   *
   * @param \Drupal\ai_connect\Service\ManifestService $manifestService
   *   The manifest service.
   * @param \GuzzleHttp\ClientInterface|null $http_client
   *   The HTTP client.
   */
  public function __construct($manifestService, ?ClientInterface $http_client = NULL) {
    $this->httpClient = $http_client ?? \Drupal::httpClient();
    parent::__construct($manifestService);
  }

  /**
   * {@inheritdoc}
   */
  protected function registerTools() {
    $this->registerTool('translate', [
      'description' => 'Translate text between languages using MyMemory translation service',
      'input_schema' => [
        'type' => 'object',
        'required' => ['text', 'target_lang'],
        'properties' => [
          'text' => [
            'type' => 'string',
            'description' => 'Text to translate',
          ],
          'source_lang' => [
            'type' => 'string',
            'description' => 'Source language code (e.g., "en", "he", "es"). Leave empty for auto-detection.',
          ],
          'target_lang' => [
            'type' => 'string',
            'description' => 'Target language code (e.g., "en", "he", "es", "fr", "de", "ru")',
          ],
        ],
      ],
    ]);

    $this->registerTool('getSupportedLanguages', [
      'description' => 'Get list of commonly supported language codes for translation',
      'input_schema' => [
        'type' => 'object',
        'properties' => [],
      ],
    ]);
  }

  /**
   * Executes translate tool.
   *
   * @param array $params
   *   Tool parameters.
   *
   * @return array
   *   Tool result.
   */
  public function executeTranslate(array $params) {
    $text = $params['text'];
    $sourceLang = $params['source_lang'] ?? '';
    $targetLang = $params['target_lang'];

    $langPair = $sourceLang ? "{$sourceLang}|{$targetLang}" : $targetLang;

    $url = self::MYMEMORY_API . '?' . http_build_query([
      'q' => $text,
      'langpair' => $langPair,
    ]);

    try {
      $response = $this->httpClient->request('GET', $url, [
        'timeout' => 10,
        'headers' => [
          'User-Agent' => 'Drupal-AIConnect/1.0',
        ],
      ]);

      $statusCode = $response->getStatusCode();

      if ($statusCode !== 200) {
        return $this->error('api_error', "Translation service returned HTTP {$statusCode}");
      }

      $body = $response->getBody()->getContents();
      $data = json_decode($body, TRUE);

      if (!$data || !isset($data['responseData'])) {
        return $this->error('invalid_response', 'Invalid response from translation service');
      }

      $responseData = $data['responseData'];

      if (!isset($responseData['translatedText'])) {
        return $this->error('translation_failed', 'Translation failed: ' . ($data['responseDetails'] ?? 'Unknown error'));
      }

      return $this->success([
        'original_text' => $text,
        'translated_text' => $responseData['translatedText'],
        'source_lang' => $sourceLang ?: 'auto',
        'target_lang' => $targetLang,
        'match' => $responseData['match'] ?? 0,
      ]);

    }
    catch (RequestException $e) {
      return $this->error('http_error', 'Failed to connect to translation service: ' . $e->getMessage());
    }
    catch (\Exception $e) {
      return $this->error('exception', 'Translation error: ' . $e->getMessage());
    }
  }

  /**
   * Executes getSupportedLanguages tool.
   *
   * @param array $params
   *   Tool parameters.
   *
   * @return array
   *   Tool result.
   */
  public function executeGetSupportedLanguages(array $params) {
    $languages = [
      'en' => 'English',
      'he' => 'Hebrew',
      'ar' => 'Arabic',
      'es' => 'Spanish',
      'fr' => 'French',
      'de' => 'German',
      'it' => 'Italian',
      'pt' => 'Portuguese',
      'ru' => 'Russian',
      'zh' => 'Chinese (Simplified)',
      'ja' => 'Japanese',
      'ko' => 'Korean',
      'nl' => 'Dutch',
      'pl' => 'Polish',
      'tr' => 'Turkish',
      'sv' => 'Swedish',
      'da' => 'Danish',
      'no' => 'Norwegian',
      'fi' => 'Finnish',
      'cs' => 'Czech',
      'ro' => 'Romanian',
      'hu' => 'Hungarian',
      'el' => 'Greek',
      'th' => 'Thai',
      'hi' => 'Hindi',
      'id' => 'Indonesian',
      'vi' => 'Vietnamese',
      'uk' => 'Ukrainian',
      'bg' => 'Bulgarian',
      'hr' => 'Croatian',
    ];

    return $this->success([
      'languages' => $languages,
      'usage' => 'Use the language code (e.g., "en", "he") in the translate tool',
    ]);
  }

}
