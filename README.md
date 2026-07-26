# zindua/sdk (PHP)

Official **server-side** SDK for [Zindua](https://zindua.run): send transactional **email** and **WhatsApp** messages with one API.

| | |
|---|---|
| Full API reference | [zindua.run/developers](https://zindua.run/developers) |
| PHP product page | [zindua.run/php](https://zindua.run/php) |
| Dashboard | [zindua.run/login](https://zindua.run/login) |

```bash
composer require zindua/sdk
```

**Requirements:** PHP 8.1+, `ext-curl`, `ext-json`. Run only on your **server** (never in the browser).

---

## Quick start

### 1. Install

```bash
composer require zindua/sdk
```

### 2. Configure

```bash
# .env — never commit real keys
ZINDUA_API_KEY=znd_live_xxxxxxxxxxxxxxxxxxxxxxxx
```

### 3. Send OTP

```php
<?php
require 'vendor/autoload.php';

use Zindua\Client;

$zindua = new Client(['apiKey' => getenv('ZINDUA_API_KEY')]);

$result = $zindua->send([
    'to' => 'user@example.com',
    'template' => 'otp-verification',
    'lang' => 'fr',
    'variables' => ['code' => '482910', 'appName' => 'MyShop'],
]);

echo $result['logId'];
```

WhatsApp:

```php
$zindua->send([
    'to' => '+243812345678',
    'channel' => 'whatsapp',
    'template' => 'otp-verification',
    'variables' => ['code' => '482910'],
]);
```

### WhatsApp anti-ban robot (required)

Zindua’s **anti-ban Guardian** paces WhatsApp OTP so burst traffic does not ban your linked number.

- Official PHP SDK cooperates with the Guardian automatically.
- Prefer a Laravel queue / cache lock for production; never fire parallel WhatsApp bursts.

Guide: [WhatsApp anti-ban Guardian](https://zindua.run/whatsapp/anti-ban)

### Upgrade (already installed?)

```bash
composer update zindua/sdk
```

```php
$zindua = new Client([
    'apiKey' => getenv('ZINDUA_API_KEY'),
]);
```

### Factory helper

```php
use Zindua\ZinduaFactory;

$zindua = ZinduaFactory::client(); // reads ZINDUA_API_KEY from env
```

---

## API surface

| Method | Endpoint | Description |
|--------|----------|-------------|
| `send()` | `POST /send` | Queue email or WhatsApp message |
| `getProject()` | `GET /project` | Project, plan, channels |
| `getTemplates()` | `GET /templates` | Synced templates |
| `connect()` | `POST /connect` | Bind API key to site URL |

---

## Laravel

```php
// config/zindua.php
return ['api_key' => env('ZINDUA_API_KEY')];

// AppServiceProvider
$this->app->singleton(Client::class, fn () => new Client([
    'apiKey' => config('zindua.api_key'),
]));
```

## WordPress custom (mu-plugin)

```php
require_once ABSPATH . 'vendor/autoload.php';

$zindua = new \Zindua\Client(['apiKey' => getenv('ZINDUA_API_KEY')]);
```

---

## Security

- `ZINDUA_API_KEY` stays on the server only
- Validation runs locally before calling the API (same rules as `@zindua/sdk` TypeScript)
- API key is never included in error messages

---

## Related

| Package | Purpose |
|---------|---------|
| `@zindua/sdk` | Node.js / TypeScript SDK |
| `@zindua/cli` | Terminal tools |
| `@zindua/create-app` | Next.js auth starter |
| `zindua-connect` | Official WordPress plugin |
