# AI Connect for Drupal

**WebMCP Bridge for Drupal** - Connect AI agents (Claude, ChatGPT, Grok, more) with OAuth 2.0

![Drupal](https://img.shields.io/badge/Drupal-9%20%7C%2010%20%7C%2011-0678BE?style=flat&logo=drupal)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?style=flat&logo=php)
![License](https://img.shields.io/badge/License-GPL--3.0-blue.svg)

**Secure authentication:** Uses OAuth 2.0 + PKCE (the same security standard as Google, Facebook, and GitHub) - your passwords stay safe and private!

---

## 🚀 Quick Start

### Installation

1. **Download the module:**
   ```bash
   cd /path/to/drupal/modules/custom
   git clone https://github.com/chgold/drupal-ai-connect.git ai_connect
   ```

2. **Enable the module:**
   ```bash
   drush en ai_connect -y
   drush cr
   ```
   
   *Or via Admin UI:* Navigate to **Extend** (`/admin/modules`), find "AI Connect", and click **Install**.

3. **That's it!** No configuration needed - OAuth tables are created automatically.

---

## 🤖 For AI Agent Users

**Already installed the module?** Here's all you need!

Just copy and paste this instruction to your AI agent (Claude, ChatGPT, etc.):

```
Connect to my Drupal site and follow the instructions at:
https://github.com/chgold/drupal-ai-connect
```

The AI agent will handle the rest - OAuth authorization, API connection, and tool discovery.

**That's it! No technical knowledge required.** ✨

---

## 🔐 OAuth 2.0 Authentication Guide

### How It Works

AI Connect uses **OAuth 2.0 Authorization Code Flow with PKCE** - the industry standard for secure API authentication:

1. AI agent requests authorization with a **code challenge** (PKCE)
2. User approves in browser → receives one-time **authorization code**
3. Agent exchanges code for **access token** (with code verifier)
4. Agent uses token for API calls

**No pre-registration needed!** Clients register automatically on first use.

---

### Step 1: Generate PKCE Parameters

```bash
# Code verifier (43-128 character random base64url string)
CODE_VERIFIER=$(openssl rand -base64 32 | tr '+/' '-_' | tr -d '=' | cut -c1-43)

# Code challenge (SHA256 hash of verifier, base64url encoded)
CODE_CHALLENGE=$(echo -n "$CODE_VERIFIER" | openssl dgst -sha256 -binary | base64 | tr '+/' '-_' | tr -d '=')

# State (for CSRF protection)
STATE=$(openssl rand -hex 16)

# Client ID (choose any unique identifier)
CLIENT_ID="my-ai-agent-$(date +%s)"
```

---

### Step 2: Authorization URL

Direct user to this URL in their browser:

```
https://yoursite.com/oauth/authorize
  ?response_type=code
  &client_id=YOUR_CLIENT_ID
  &redirect_uri=urn:ietf:wg:oauth:2.0:oob
  &scope=read%20write
  &state=YOUR_STATE
  &code_challenge=YOUR_CODE_CHALLENGE
  &code_challenge_method=S256
```

**Available scopes:**
- `read` - Read content, comments, and user info
- `write` - Create and update content
- `delete` - Delete content

**What happens:**
1. User logs into Drupal (if not already logged in)
2. Sees consent screen asking to authorize your app
3. Clicks "Approve"
4. Receives **authorization code** on screen (valid for 10 minutes)

**💡 Localhost Support:** For MCP clients (Claude Desktop, etc.), you can use `redirect_uri=http://localhost:PORT/callback` - the server automatically allows any `localhost` URL!

---

### Step 3: Exchange Code for Token

**Request:**
```bash
curl -X POST "https://yoursite.com/api/ai-connect/v1/oauth/token" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=authorization_code" \
  -d "client_id=$CLIENT_ID" \
  -d "code=AUTHORIZATION_CODE_HERE" \
  -d "redirect_uri=urn:ietf:wg:oauth:2.0:oob" \
  -d "code_verifier=$CODE_VERIFIER"
```

**Response:**
```json
{
  "access_token": "dpc_c6c9f8398c5f7921713011d19676ee2f81470cf7ec7c71ce91925cd129853dd3",
  "token_type": "Bearer",
  "expires_in": 3600,
  "refresh_token": "dpr_8a7b6c5d4e3f2a1b9c8d7e6f5a4b3c2d1e0f9a8b7c6d5e4f3a2b1c0d9e8f7a6b",
  "refresh_token_expires_in": 2592000,
  "scope": "read write"
}
```

**⚠️ Security Notes:**
- Authorization codes are **one-time use** and expire in **10 minutes**
- Access tokens expire after **1 hour**
- Refresh tokens expire after **30 days**
- **PKCE verification** ensures only the client that initiated the flow can claim the token
- **Save your refresh token** - you'll need it to get new access tokens without re-authenticating!

---

### Step 4: Use the API

**Request:**
```bash
curl -X POST "https://yoursite.com/api/ai-connect/v1/tools/drupal.getCurrentUser" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -d '{}'
```

**Response:**
```json
{
  "success": true,
  "data": {
    "user_id": "1",
    "username": "admin",
    "email": "admin@example.com",
    "roles": ["authenticated", "administrator"],
    "created": "1640000000",
    "last_access": "1640100000"
  },
  "message": null
}
```

---

### Step 5: Refresh Access Token (After 1 Hour)

Access tokens expire after 1 hour. Use your refresh token to get a new one without re-authenticating:

**Request:**
```bash
curl -X POST "https://yoursite.com/api/ai-connect/v1/oauth/token" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=refresh_token" \
  -d "client_id=$CLIENT_ID" \
  -d "refresh_token=dpr_8a7b6c5d4e3f2a1b..."
```

**Response:**
```json
{
  "access_token": "dpc_NEW_ACCESS_TOKEN_HERE",
  "token_type": "Bearer",
  "expires_in": 3600,
  "refresh_token": "dpr_NEW_REFRESH_TOKEN_HERE",
  "refresh_token_expires_in": 2592000,
  "scope": "read write"
}
```

**Important:**
- The old access token and refresh token are **automatically revoked**
- You receive **new access token AND refresh token**
- Refresh tokens are valid for **30 days**
- If the refresh token expires, user must **re-authorize from Step 1**

---

### Step 6: Revoke Token (Optional)

Revoke an access token when you're done or if it's compromised:

**Request:**
```bash
curl -X POST "https://yoursite.com/api/ai-connect/v1/oauth/revoke" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "token=dpc_c6c9f8398c5f7921..."
```

**Response:**
```json
{
  "success": true
}
```

**Note:** Revoking an access token also revokes its associated refresh token.

---

## 🛠️ Available Tools

### 1. `drupal.searchNodes`
Search Drupal content nodes with filters.

**Parameters:**
- `type` (string, optional) - Content type machine name (e.g., `article`, `page`)
- `search` (string, optional) - Search query for node titles
- `limit` (integer, optional) - Max results (default: 10)
- `offset` (integer, optional) - Skip results for pagination (default: 0)

**Example:**
```bash
curl -X POST "https://yoursite.com/api/ai-connect/v1/tools/drupal.searchNodes" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "search": "technology",
    "type": "article",
    "limit": 10
  }'
```

---

### 2. `drupal.getNode`
Get a single node by ID.

**Parameters:**
- `node_id` (integer, required) - Node ID (nid)

**Example:**
```bash
curl -X POST "https://yoursite.com/api/ai-connect/v1/tools/drupal.getNode" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"node_id": 1}'
```

**Response:**
```json
{
  "success": true,
  "data": {
    "node_id": "1",
    "title": "Welcome to Drupal",
    "content_type": "article",
    "body": "Full content here...",
    "summary": "Brief summary...",
    "author": {
      "user_id": "1",
      "username": "admin"
    },
    "created": "2024-01-15T10:30:00+00:00",
    "changed": "2024-01-15T10:30:00+00:00",
    "published": true,
    "url": "https://yoursite.com/node/1"
  }
}
```

---

### 3. `drupal.searchComments`
Search Drupal comments.

**Parameters:**
- `search` (string, optional) - Search query for comment subjects
- `node_id` (integer, optional) - Filter by node ID
- `limit` (integer, optional) - Max results (default: 10)

**Example:**
```bash
curl -X POST "https://yoursite.com/api/ai-connect/v1/tools/drupal.searchComments" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"node_id": 1, "limit": 10}'
```

---

### 4. `drupal.getComment`
Get a single comment by ID.

**Parameters:**
- `comment_id` (integer, required) - Comment ID (cid)

**Example:**
```bash
curl -X POST "https://yoursite.com/api/ai-connect/v1/tools/drupal.getComment" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"comment_id": 1}'
```

---

### 5. `drupal.getCurrentUser`
Get information about the authenticated user.

**Parameters:** None

**Example:**
```bash
curl -X POST "https://yoursite.com/api/ai-connect/v1/tools/drupal.getCurrentUser" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{}'
```

---

### 6. `translation.getSupportedLanguages`
Get list of supported language codes for translation.

**Parameters:** None

**Example:**
```bash
curl -X POST "https://yoursite.com/api/ai-connect/v1/tools/translation.getSupportedLanguages" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{}'
```

**Response:**
```json
{
  "success": true,
  "data": {
    "languages": {
      "en": "English",
      "he": "Hebrew",
      "ar": "Arabic",
      "es": "Spanish",
      "fr": "French",
      "de": "German",
      "it": "Italian",
      "pt": "Portuguese",
      "ru": "Russian",
      "zh": "Chinese (Simplified)",
      "ja": "Japanese",
      "ko": "Korean"
    }
  }
}
```

---

### 7. `translation.translate`
Translate text between languages using MyMemory translation service.

**Parameters:**
- `text` (string, required) - Text to translate
- `source_lang` (string, required) - Source language code (e.g., `en`)
- `target_lang` (string, required) - Target language code (e.g., `he`)

**Example:**
```bash
curl -X POST "https://yoursite.com/api/ai-connect/v1/tools/translation.translate" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "text": "Hello, how are you?",
    "source_lang": "en",
    "target_lang": "he"
  }'
```

**Response:**
```json
{
  "success": true,
  "data": {
    "original_text": "Hello, how are you?",
    "translated_text": "שלום, מה שלומך?",
    "source_lang": "en",
    "target_lang": "he",
    "match": 0.95
  }
}
```

---

## 🔐 Admin Controls

### Security Features

Navigate to **Configuration → Web Services → AI Connect** (`/admin/config/services/ai-connect`) to manage security:

#### 1. View OAuth Clients

- See all registered OAuth clients
- View client IDs, names, and redirect URIs
- Monitor when clients were created

#### 2. Manage Access Tokens

- View active OAuth sessions
- See which users have authorized which clients
- Monitor token expiration times

#### 3. Revoke Access

To revoke a user's access:
1. Go to **Configuration → Web Services → AI Connect → OAuth Clients**
2. Find the client or token
3. Click "Delete" or manually revoke from database

**Result:** User cannot use existing tokens and must re-authorize.

---

## 📊 Rate Limiting

**Default limits (per user):**
- **50 requests per minute**
- **1,000 requests per hour**

Configure in: **Configuration → Web Services → AI Connect** (`/admin/config/services/ai-connect`)

**Rate limit response:**
```json
{
  "error": "rate_limit_exceeded",
  "message": "Rate limit exceeded: 50 requests per minute",
  "retry_after": 45
}
```

---

## 🔐 Security Best Practices

### For Site Administrators

✅ **Create dedicated AI user accounts** - Don't use your admin account  
✅ **Use strong passwords** - Or Drupal's application passwords  
✅ **Monitor OAuth clients** - Review authorized apps regularly  
✅ **Enable HTTPS** - Encrypt all traffic in production  
✅ **Set appropriate permissions** - Limit which roles can use the API

### For Developers

✅ **Store credentials securely** - Use environment variables, never hardcode  
✅ **Handle token expiry gracefully** - Implement automatic refresh  
✅ **Respect rate limits** - Cache responses when possible  
✅ **Use HTTPS endpoints** - Never send credentials over HTTP  
✅ **Rotate refresh tokens** - Get new ones periodically

---

## 🐛 Troubleshooting

### Common OAuth Errors

**"Invalid client_id"**  
**Solution:** Client was never registered. Visit the authorization URL first to auto-register.

**"Authorization code not found"**  
**Solution:**
- Authorization codes are **one-time use** and expire after **10 minutes**
- Request a new authorization code
- Ensure you haven't already used this code

**"PKCE verification failed"**  
**Solution:** Ensure you're using the **same code_verifier** that generated the code_challenge.

**"Token has been revoked"**  
**Solution:** Token was manually revoked. User must re-authorize from Step 1.

**"Token expired"**  
**Solution:** Access tokens expire after 1 hour. Use your refresh token to get a new one.

**"Rate limit exceeded"**  
**Solution:**
- Wait for retry period (check `retry_after` in response)
- Increase limits in **Configuration → Web Services → AI Connect**

---

### Routes Not Found (404)

**Solution:**
```bash
drush cr
# Or via Admin UI:
# Configuration → Development → Performance → Clear all caches
```

---

### OAuth Tables Don't Exist

**Error:** `Table 'drupal.ai_connect_oauth_clients' doesn't exist`

**Solution:**
```bash
# Run database updates
drush updatedb -y

# Or manually create tables
drush php:eval "
  \$schema = \Drupal::service('extension.list.module')->get('ai_connect')->schema();
  \$db = \Drupal::database();
  foreach (['ai_connect_oauth_clients', 'ai_connect_oauth_codes', 'ai_connect_oauth_tokens'] as \$table) {
    if (!\$db->schema()->tableExists(\$table)) {
      \$db->schema()->createTable(\$table, \$schema[\$table]);
    }
  }
"
drush cr
```

---

## 🔧 For Developers

### Add Custom Tools

Use Drupal's event system to register custom tools:

```php
<?php

/**
 * Implements hook_ai_connect_register_tools().
 */
function mymodule_ai_connect_register_tools($manifest) {
  $manifest->registerTool('mymodule.customTool', [
    'description' => 'My custom tool',
    'input_schema' => [
      'type' => 'object',
      'properties' => [
        'param1' => [
          'type' => 'string',
          'description' => 'First parameter'
        ]
      ],
      'required' => ['param1']
    ]
  ]);
}
```

**Important:** Custom tools are preserved during module updates. Place your code in a custom module to ensure it persists.

---

### Module Architecture

```
ai_connect/
├── src/
│   ├── Controller/           # API endpoints
│   │   ├── ManifestController.php
│   │   ├── OAuthController.php
│   │   └── ToolsController.php
│   ├── Service/              # Core services
│   │   ├── ManifestService.php
│   │   ├── OAuthService.php
│   │   ├── ModuleManager.php
│   │   └── RateLimiterService.php
│   ├── Module/               # Tool modules
│   │   ├── ModuleBase.php
│   │   ├── CoreModule.php
│   │   └── TranslationModule.php
│   └── Form/
│       ├── SettingsForm.php
│       ├── OAuthClientForm.php
│       └── OAuthClientDeleteForm.php
├── templates/                # Twig templates
│   ├── ai-connect-oauth-consent.html.twig
│   └── ai-connect-oauth-oob.html.twig
├── ai_connect.info.yml       # Module metadata
├── ai_connect.module          # Hook implementations
├── ai_connect.routing.yml     # Route definitions
├── ai_connect.services.yml    # Service container
├── ai_connect.permissions.yml # Permissions
├── ai_connect.install         # Schema & install hooks
└── ai_connect.links.menu.yml  # Admin menu
```

---

## 📋 Changelog

### Version 1.0.0 - 2026-02-24
- **Security:** Migrated to OAuth 2.0 with PKCE for secure authentication
- **Added:** Auto-registration of OAuth clients (no pre-configuration needed)
- **Added:** Localhost redirect URI support for MCP clients
- **Added:** Token lifecycle management (refresh, revocation)
- **Added:** Translation tools (getSupportedLanguages, translate)
- **Improved:** All 7 tools now use OAuth authentication
- **Improved:** Comprehensive security validation

### Version 0.1.0 - 2026-02-17
- Initial release
- WebMCP protocol support
- JWT authentication
- 5 Drupal core tools

---

## 💬 We Need Your Feedback!

Help us build what YOU need:

💡 **What tools would be most useful?** Tell us which Drupal features you'd like AI agents to access  
🐛 **Found a bug?** Report it so we can fix it quickly  
⭐ **Feature requests** - We prioritize based on community feedback

**How to provide feedback:**
- **GitHub Issues** - [https://github.com/chgold/drupal-ai-connect/issues](https://github.com/chgold/drupal-ai-connect/issues)
- **Drupal.org** - Project page discussions

Your feedback directly shapes the future of this module!

---

## 🤝 Contributing

This module is maintained by a single developer.

To ensure code quality and maintain a consistent architecture, we do not accept code contributions (Pull Requests) at this time.

**How you can help:**

🐛 **Report bugs** - Open an issue with reproduction steps  
💡 **Request features** - Tell us what would make the module better  
⭐ **Spread the word** - Rate, review, and share if you find it useful  
📖 **Improve docs** - Suggest clarifications or corrections

Thank you for your understanding!

---

## 📄 License

GPL-3.0-or-later

Compatible with Drupal 9, 10, and 11.

---

## 🌟 Made with ❤️ for the Drupal & AI community

**Questions?** Open an issue on GitHub or start a discussion on Drupal.org.

**Using this module?** We'd love to hear your success story!
