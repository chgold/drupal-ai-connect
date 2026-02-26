# חיבור AI עבור דרופל

**גשר WebMCP עבור דרופל** - חיבור סוכני בינה מלאכותית (Claude, ChatGPT, Grok ועוד) עם OAuth 2.0

![Drupal](https://img.shields.io/badge/Drupal-9%20%7C%2010%20%7C%2011-0678BE?style=flat&logo=drupal)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?style=flat&logo=php)
![License](https://img.shields.io/badge/License-GPL--3.0-blue.svg)

**אימות מאובטח:** משתמש ב-OAuth 2.0 + PKCE (אותו תקן אבטחה כמו גוגל, פייסבוק ו-GitHub) - הסיסמאות שלך נשארות בטוחות ופרטיות!

---

## 🚀 התחלה מהירה

### הַתקָנָה

1. **הורד את המודול:**
באש
cd /path/to/drupal/modules/custom
שיבוט גיט https://github.com/chgold/drupal-ai-connect.git ai_connect
```

2. **הפעל את המודול:**
באש
drush ב- ai_connect -y
דרוש קר
```
   
*או דרך ממשק המשתמש של ניהול המערכת:* נווטו אל **Extend** ‏(`/admin/modules`), מצאו את "AI Connect" ולחצו על **Install**.

3. **זהו!** אין צורך בהגדרה - טבלאות OAuth נוצרות באופן אוטומטי.

---

## 🤖 למשתמשי סוכני בינה מלאכותית

**כבר התקנת את המודול?** הנה כל מה שאתה צריך!

פשוט העתיקו והדביקו את ההוראה הזו לסוכן הבינה המלאכותית שלכם (Claude, ChatGPT וכו'):

```
Connect to my Drupal site and follow the instructions at:
https://github.com/chgold/drupal-ai-connect
```

סוכן הבינה המלאכותית יטפל בשאר - הרשאת OAuth, חיבור API וגילוי כלים.

**זהו! אין צורך בידע טכני.** ✨

---

## 🔐 מדריך אימות OAuth 2.0

### איך זה עובד

AI Connect משתמש ב- **OAuth 2.0 Authorization Code Flow עם PKCE** - הסטנדרט בתעשייה לאימות API מאובטח:

1. סוכן בינה מלאכותית מבקש אישור עם **אתגר קוד** (PKCE)
2. המשתמש מאשר בדפדפן → מקבל קוד אישור חד פעמי
3. סוכן מחליף קוד עבור **אסימון גישה** (עם מאמת קוד)
4. הסוכן משתמש באסימון עבור קריאות API

**אין צורך ברישום מראש!** לקוחות נרשמים אוטומטית בשימוש הראשון.

---

### שלב 1: יצירת פרמטרים של PKCE

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

### שלב 2: כתובת URL לאימות

הפנה את המשתמש לכתובת URL זו בדפדפן שלו:

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

**היקף זמין:**
- `read` - קרא תוכן, תגובות ופרטי משתמש
- `כתיבה` - יצירה ועדכון של תוכן
- `מחק` - מחיקת תוכן

**מה קורה:**
1. משתמש מתחבר לדרופל (אם עדיין לא מחובר)
2. רואה מסך הסכמה שמבקש לאשר את האפליקציה שלך
3. לוחץ על "אישור"
4. מקבל **קוד אישור** על המסך (תקף ל-10 דקות)

**💡 תמיכה ב-Localhost:** עבור לקוחות MCP (Claude Desktop וכו'), ניתן להשתמש ב-`redirect_uri=http://localhost:PORT/callback` - השרת מאפשר אוטומטית כל כתובת URL של `localhost`!

---

### שלב 3: החלפת קוד עבור טוקן

**בַּקָשָׁה:**
```bash
curl -X POST "https://yoursite.com/api/ai-connect/v1/oauth/token" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=authorization_code" \
  -d "client_id=$CLIENT_ID" \
  -d "code=AUTHORIZATION_CODE_HERE" \
  -d "redirect_uri=urn:ietf:wg:oauth:2.0:oob" \
  -d "code_verifier=$CODE_VERIFIER"
```

**תְגוּבָה:**
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

**⚠️ הערות אבטחה:**
- קודי הרשאה הם **חד פעמיים** ופוגים תוך **10 דקות**
- אסימוני גישה פגים לאחר **שעה**
- אסימוני רענון פגים לאחר **30 יום**
- **אימות PKCE** מבטיח שרק הלקוח שיזם את הזרימה יוכל לתבוע את האסימון
- **שמור את אסימון הרענון שלך** - תצטרך אותו כדי לקבל אסמוני גישה חדשים מבלי לבצע אימות מחדש!

---

### שלב 4: שימוש ב-API

**בַּקָשָׁה:**
```bash
curl -X POST "https://yoursite.com/api/ai-connect/v1/tools/drupal.getCurrentUser" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -d '{}'
```

**תְגוּבָה:**
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

### שלב 5: רענון אסימון הגישה (לאחר שעה)

תוקפם של טוקנים לגישה פג לאחר שעה. השתמשו באסימון הרענון שלכם כדי לקבל אחד חדש מבלי לבצע אימות מחדש:

**בַּקָשָׁה:**
```bash
curl -X POST "https://yoursite.com/api/ai-connect/v1/oauth/token" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=refresh_token" \
  -d "client_id=$CLIENT_ID" \
  -d "refresh_token=dpr_8a7b6c5d4e3f2a1b..."
```

**תְגוּבָה:**
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

**חָשׁוּב:**
- אסימון הגישה הישן ואסימון הרענון **בוטלו אוטומטית**
- אתה מקבל **אסימון גישה חדש וגם אסימון רענון**
- אסימוני רענון תקפים למשך **30 יום**
- אם תוקף אסימון הרענון פג, על המשתמש **להרשות מחדש משלב 1**

---

### שלב 6: ביטול אסימון (אופציונלי)

בטל אסימון גישה לאחר שתסיים או אם הוא נפרץ:

**בַּקָשָׁה:**
```bash
curl -X POST "https://yoursite.com/api/ai-connect/v1/oauth/revoke" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "token=dpc_c6c9f8398c5f7921..."
```

**תְגוּבָה:**
```json
{
  "success": true
}
```

**הערה:** ביטול אסימון גישה מבטל גם את אסימון הרענון המשויך אליו.

---

## 🛠️ כלים זמינים

### 1. `drupal.searchNodes`
חפש צמתי תוכן של דרופל באמצעות מסננים.

**פרמטרים:**
- `type` (מחרוזת, אופציונלי) - שם מכונה של סוג התוכן (לדוגמה, `article`, `page`)
- `search` (מחרוזת, אופציונלי) - שאילתת חיפוש עבור כותרות צמתים
- `limit` (מספר שלם, אופציונלי) - תוצאות מקסימליות (ברירת מחדל: 10)
- `offset` (מספר שלם, אופציונלי) - דילוג על תוצאות עבור דפדוף (ברירת מחדל: 0)

**דוּגמָה:**
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
קבל צומת בודד לפי מזהה.

**פרמטרים:**
- `node_id` (מספר שלם, נדרש) - מזהה צומת (nid)

**דוּגמָה:**
```bash
curl -X POST "https://yoursite.com/api/ai-connect/v1/tools/drupal.getNode" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"node_id": 1}'
```

**תְגוּבָה:**
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
חפש תגובות בדרופל.

**פרמטרים:**
- `search` (מחרוזת, אופציונלי) - שאילתת חיפוש עבור נושאי תגובות
- `node_id` (מספר שלם, אופציונלי) - סינון לפי מזהה צומת
- `limit` (מספר שלם, אופציונלי) - תוצאות מקסימליות (ברירת מחדל: 10)

**דוּגמָה:**
```bash
curl -X POST "https://yoursite.com/api/ai-connect/v1/tools/drupal.searchComments" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"node_id": 1, "limit": 10}'
```

---

### 4. `drupal.getComment`
קבל תגובה בודדת לפי תעודת זהות.

**פרמטרים:**
- `comment_id` (מספר שלם, נדרש) - מזהה תגובה (cid)

**דוּגמָה:**
```bash
curl -X POST "https://yoursite.com/api/ai-connect/v1/tools/drupal.getComment" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"comment_id": 1}'
```

---

### 5. `drupal.getCurrentUser`
קבל מידע על המשתמש המאומת.

**פרמטרים:** אין

**דוּגמָה:**
```bash
curl -X POST "https://yoursite.com/api/ai-connect/v1/tools/drupal.getCurrentUser" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{}'
```

---

### 6. `translation.getSupportedLanguages`
קבל רשימה של קודי שפה נתמכים לתרגום.

**פרמטרים:** אין

**דוּגמָה:**
```bash
curl -X POST "https://yoursite.com/api/ai-connect/v1/tools/translation.getSupportedLanguages" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{}'
```

**תְגוּבָה:**
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

### 7. `תרגום.תרגם`
תרגם טקסט בין שפות באמצעות שירות התרגום MyMemory.

**פרמטרים:**
- `text` (מחרוזת, נדרש) - טקסט לתרגום
- `source_lang` (מחרוזת, חובה) - קוד שפת המקור (לדוגמה, `en`)
- `target_lang` (מחרוזת, חובה) - קוד שפת היעד (לדוגמה, `he`)

**דוּגמָה:**
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

**תְגוּבָה:**
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

## 🔐 בקרות מנהל

### תכונות אבטחה

נווט אל **תצורה ← שירותי אינטרנט ← חיבור בינה מלאכותית** ‏(`/admin/config/services/ai-connect`) כדי לנהל את האבטחה:

#### 1. צפה בלקוחות OAuth

- ראה את כל לקוחות OAuth הרשומים
- הצג מזהי לקוחות, שמות וכתובות URI להפניה
- ניטור מועד יצירת הלקוחות

#### 2. ניהול אסימוני גישה

- הצג הפעלות OAuth פעילות
- ראה אילו משתמשים אישרו אילו לקוחות
- ניטור זמני תפוגת אסימונים

#### 3. ביטול גישה

כדי לבטל גישת משתמש:
1. עבור אל **תצורה ← שירותי אינטרנט ← חיבור AI ← לקוחות OAuth**
2. מצא את הלקוח או הטוקן
3. לחץ על "מחק" או בטל ידנית ממסד הנתונים

**תוצאה:** המשתמש אינו יכול להשתמש באסימונים קיימים ועליו לאשר מחדש.

---

## 📊 הגבלת קצב

**מגבלות ברירת מחדל (למשתמש):**
- **50 בקשות לדקה**
- **1,000 בקשות לשעה**

קבע תצורה ב: **תצורה ← שירותי אינטרנט ← חיבור AI** (`/admin/config/services/ai-connect`)

**תגובת מגבלת קצב:**
```json
{
  "error": "rate_limit_exceeded",
  "message": "Rate limit exceeded: 50 requests per minute",
  "retry_after": 45
}
```

---

## 🔐 שיטות עבודה מומלצות לאבטחה

### למנהלי אתרים

✅ **צור חשבונות משתמש ייעודיים לבינה מלאכותית** - אל תשתמש בחשבון המנהל שלך
✅ **השתמשו בסיסמאות חזקות** - או בסיסמאות של אפליקציות דרופל
✅ **ניטור לקוחות OAuth** - בדיקת אפליקציות מורשות באופן קבוע
✅ **הפעל HTTPS** - הצפן את כל התעבורה בסביבת הייצור
✅ **הגדרת הרשאות מתאימות** - הגבל אילו תפקידים יכולים להשתמש ב-API

### למפתחים

✅ **אחסן אישורים בצורה מאובטחת** - השתמש במשתני סביבה, לעולם לא בקוד קשיח
✅ **טיפול יעיל בתוקף טוקנים** - יישום רענון אוטומטי
✅ **כבדו את מגבלות הקצב** - שמרו תגובות במטמון במידת האפשר
✅ **השתמש בנקודות קצה של HTTPS** - לעולם אל תשלח אישורים דרך HTTP
✅ **סובב אסימוני רענון** - קבל חדשים מעת לעת

---

## 🐛 פתרון בעיות

### שגיאות OAuth נפוצות

**"מזהה_לקוח_לא_תקין"**
**פתרון:** הלקוח מעולם לא נרשם. בקר תחילה בכתובת ה-URL של ההרשאה כדי להירשם אוטומטית.

**קוד האישור לא נמצא**
**פִּתָרוֹן:**
- קודי הרשאה הם **חד פעמיים** ופג תוקפם לאחר **10 דקות**
- בקשת קוד אישור חדש
- ודא שעדיין לא השתמשת בקוד הזה

**אימות PKCE נכשל**
**פתרון:** ודא שאתה משתמש באותו code_verifier שיצר את code_challenge.

**"האסימון בוטל"**
**פתרון:** האסימון בוטל ידנית. על המשתמש לאשר מחדש משלב 1.

**"פג תוקפו של האסימון"**
**פתרון:** תוקפם של טוקני גישה פג לאחר שעה. השתמשו באסימון הרענון שלכם כדי לקבל חדש.

**חריגה ממגבלת התעריף**
**פִּתָרוֹן:**
- המתן לזמן ניסיון חוזר (סמן `retry_after` בתגובה)
- הגדלת מגבלות ב- **תצורה ← שירותי אינטרנט ← חיבור בינה מלאכותית**

---

### מסלולים שלא נמצאו (404)

**פִּתָרוֹן:**
```bash
drush cr
# Or via Admin UI:
# Configuration → Development → Performance → Clear all caches
```

---

### טבלאות OAuth אינן קיימות

**שגיאה:** `הטבלה 'drupal.ai_connect_oauth_clients' אינה קיימת`

**פִּתָרוֹן:**
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

## 🔧 למפתחים

### הוסף כלים מותאמים אישית

השתמשו במערכת האירועים של דרופל כדי לרשום כלים מותאמים אישית:

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

**חשוב:** כלים מותאמים אישית נשמרים במהלך עדכוני מודולים. יש להציב את הקוד במודול מותאם אישית כדי להבטיח שהוא יישאר בתוקף.

---

### ארכיטקטורת מודולים

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

## 📋 יומן שינויים

### גרסה 1.0.0 - 24-02-2026
- **אבטחה:** עברנו ל-OAuth 2.0 עם PKCE לאימות מאובטח
- **נוסף:** רישום אוטומטי של לקוחות OAuth (אין צורך בהגדרה מראש)
- **נוסף:** תמיכה ב-URI להפניה מחדש של Localhost עבור לקוחות MCP
- **נוסף:** ניהול מחזור חיי טוקנים (רענון, ביטול)
- **נוספו:** כלי תרגום (getSupportedLanguages, translate)
- **שיפור:** כל 7 הכלים משתמשים כעת באימות OAuth
- **משופר:** אימות אבטחה מקיף

### גרסה 0.1.0 - 17-02-2026
- שחרור ראשוני
- תמיכה בפרוטוקול WebMCP
- אימות JWT
- 5 כלי ליבה של דרופל

---

## 💬 אנחנו צריכים את המשוב שלכם!

עזרו לנו לבנות את מה שאתם צריכים:

💡 **אילו כלים יהיו הכי שימושיים?** ספרו לנו לאילו תכונות של דרופל תרצו שסוכני בינה מלאכותית ייגשו
🐛 **מצאת באג?** דווח על כך כדי שנוכל לתקן אותו במהירות
⭐ **בקשות לתכונות** - אנו מתעדפים על סמך משוב מהקהילה

**כיצד לתת משוב:**
- **בעיות ב-GitHub** - [https://github.com/chgold/drupal-ai-connect/issues](https://github.com/chgold/drupal-ai-connect/issues)
- **Drupal.org** - דיונים על דפי הפרויקט

המשוב שלך מעצב ישירות את עתידו של מודול זה!

---

## 🤝 תרומה

מודול זה מתוחזק על ידי מפתח יחיד.

כדי להבטיח את איכות הקוד ולשמור על ארכיטקטורה עקבית, איננו מקבלים תרומות קוד (Pull Requests) כרגע.

**איך תוכלו לעזור:**

🐛 **דווח על באגים** - פתח בעיה עם שלבי שכפול
💡 **בקשת תכונות** - ספרו לנו מה ישפר את המודול
⭐ **הפיצו את הבשורה** - דרגו, סקרו ושתפו אם אתם מוצאים את זה מועיל
📖 **שיפור מסמכים** - הצעת הבהרות או תיקונים

תודה על ההבנה!

---

## 📄 רישיון

GPL-3.0 או גרסה מתקדמת יותר

תואם לדרופל 9, 10 ו-11.

---

## 🌟 נוצר באמצעות ❤️ עבור קהילת דרופל ובינה מלאכותית

**שאלות?** פתחו נושא ב-GitHub או התחלו דיון ב-Drupal.org.

**משתמשים במודול הזה?** נשמח לשמוע את סיפור ההצלחה שלכם!
