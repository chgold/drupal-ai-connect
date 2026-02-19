# AI Connect for Drupal

WebMCP protocol bridge for Drupal - enables AI agents to interact with your Drupal site via standardized API.

## Features

- **WebMCP Protocol Support**: Fully compliant with WebMCP manifest specification
- **JWT Authentication**: Secure token-based authentication
- **Rate Limiting**: Configurable per-minute and per-hour rate limits
- **Modular Architecture**: Extensible module system for adding custom tools
- **5 Core Tools**: 
  - `drupal.searchNodes` - Search content nodes with filters
  - `drupal.getNode` - Get single node by ID
  - `drupal.searchComments` - Search comments
  - `drupal.getComment` - Get single comment by ID
  - `drupal.getCurrentUser` - Get authenticated user info

## Requirements

- Drupal 9.x or 10.x
- PHP 7.4+
- Composer

## Installation

1. **Clone or download the module**:
   ```bash
   cd /path/to/drupal/modules/custom
   git clone https://github.com/chgold/drupal-ai-connect.git ai_connect
   cd ai_connect
   ```

2. **Install JWT dependency**:
   ```bash
   cd /path/to/drupal
   composer require firebase/php-jwt
   ```
   
   **Important:** The JWT library must be manually registered in Drupal's autoloader. Add to `/vendor/composer/autoload_static.php`:
   ```php
   'Firebase\\JWT\\' => array($vendorDir . '/firebase/php-jwt/src'),
   ```

3. **Enable the module**:
   ```bash
   drush en ai_connect
   drush cr
   ```

2. **Configure settings**:
   - Navigate to `/admin/config/services/ai-connect`
   - Review JWT secret (auto-generated on install)
   - Configure rate limits if needed

3. **Grant permissions**:
   - Go to `/admin/people/permissions`
   - Grant "Use AI Connect API" permission to appropriate roles

## Usage

### Get WebMCP Manifest

```bash
curl https://your-drupal-site.com/api/ai-connect/manifest
```

### Authenticate

```bash
curl -X POST https://your-drupal-site.com/api/ai-connect/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "username": "your_username",
    "password": "your_password"
  }'
```

Response:
```json
{
  "success": true,
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "token_type": "Bearer",
  "expires_in": 3600,
  "user_id": 1,
  "username": "admin"
}
```

### Use Tools

```bash
curl -X POST https://your-drupal-site.com/api/ai-connect/v1/tools/drupal.searchNodes \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "search": "welcome",
    "content_type": "article",
    "limit": 5
  }'
```

## Available Tools

### drupal.searchNodes
Search Drupal content nodes with optional filters.

**Parameters**:
- `search` (string, optional): Search query for node titles
- `content_type` (string, optional): Content type machine name
- `limit` (integer, optional): Max results (default: 10)

### drupal.getNode
Get a single node by ID.

**Parameters**:
- `node_id` (integer, required): Node ID

### drupal.searchComments
Search Drupal comments.

**Parameters**:
- `search` (string, optional): Search query for comment subjects
- `node_id` (integer, optional): Filter by node ID
- `limit` (integer, optional): Max results (default: 10)

### drupal.getComment
Get a single comment by ID.

**Parameters**:
- `comment_id` (integer, required): Comment ID

### drupal.getCurrentUser
Get current authenticated user information.

**Parameters**: None

## Architecture

```
drupal-ai-connect/
├── src/
│   ├── Controller/          # REST API endpoints
│   │   ├── AuthController.php
│   │   ├── ManifestController.php
│   │   └── ToolsController.php
│   ├── Service/             # Core services
│   │   ├── AuthService.php
│   │   ├── ManifestService.php
│   │   ├── RateLimiterService.php
│   │   └── ModuleManager.php
│   ├── Module/              # Tool modules
│   │   ├── ModuleBase.php
│   │   └── CoreModule.php
│   └── Form/
│       └── SettingsForm.php
├── ai_connect.info.yml      # Module metadata
├── ai_connect.module         # Hook implementations
├── ai_connect.routing.yml    # Route definitions
├── ai_connect.services.yml   # Service container
├── ai_connect.permissions.yml
├── ai_connect.install        # Database schema
└── composer.json
```

## Extending with Custom Modules

Create custom tool modules by extending `ModuleBase`:

```php
<?php

namespace Drupal\my_module\AIConnect;

use Drupal\ai_connect\Module\ModuleBase;

/**
 *
 */
class MyModule extends ModuleBase {

  protected $moduleName = 'mymodule';

  /**
   *
   */
  protected function registerTools() {
    $this->registerTool('myTool', [
      'description' => 'My custom tool',
      'input_schema' => [
        'type' => 'object',
        'properties' => [
          'param' => ['type' => 'string'],
        ],
      ],
    ]);
  }

  /**
   *
   */
  public function execute_myTool($params) {
    return $this->success(['result' => 'data']);
  }

}
```

Register in your module's services.yml and hook into AI Connect's module manager.

## Security

- **JWT Tokens**: All API requests require valid JWT authentication
- **Rate Limiting**: Default 50 req/min, 1000 req/hour per user
- **Blocked Users**: Admin can block specific users from API access
- **Permissions**: Drupal's permission system controls API access
- **Published Content Only**: Only public/published content is accessible

## Configuration

### Settings (` / admin / config / services / ai - connect`)
- **JWT Secret**: Secret key for token generation (64-char hex)
- **Token Expiry**: Token lifetime in seconds (300-86400)
- **Rate Limit Per Minute**: Max requests per minute (1-1000)
- **Rate Limit Per Hour**: Max requests per hour (10-100000)

## Database Tables

- `ai_connect_api_keys`: Stores persistent API keys
- `ai_connect_rate_limits`: Tracks rate limit windows
- `ai_connect_blocked_users`: Lists blocked users

## Troubleshooting

### firebase/php-jwt not found

**Error:** `

/**
 *
 */
class 'Firebase\JWT\JWT' not found`

**Fix:** Manually register the JWT library in Drupal's autoloader:

1. Open ` / vendor / composer / autoload_static . php`
2. Find the `$prefixLengthsPsr4` array
3. Add:
   ```php
   'Firebase\\JWT\\' => [$vendorDir . '/firebase/php-jwt/src'],
   ```

### Tools return authentication errors

**Error:** JWT validation fails or user context missing

**Fix:** The `ToolsController` automatically switches to the authenticated user from the JWT token. Ensure:
- JWT secret matches between config and auth requests
- User exists in Drupal
- User has "Use AI Connect API" permission

### ModuleManager doesn't find CoreModule

**Fix:** `CoreModule` is auto-registered in `ModuleManager::__construct()`. If you see this error after an update:
```bash
drush cr
drush config:export
```

## Development

This module follows Drupal coding standards and best practices:
- PSR-4 autoloading
- Dependency injection via services
- Drupal's entity API
- Configuration API for settings

## License

GPL-3.0-or-later

## Support

For issues, feature requests, or contributions:
- Submit issues on Drupal.org project page
- Follow Drupal contribution guidelines

## Credits

Developed as part of the multi-platform AI Connect initiative to bring WebMCP protocol support to major CMS/forum platforms.
