<h1 align="center">SendCloud</h1>

<p align="center">:e-mail: [SendCloud](https://www.sendcloud.net) Mail SDK</p>

## Installing

```shell
$ composer require overtrue/sendcloud -vvv
```

## Usage

```php
use Overtrue\SendCloud\SendCloud;

$apiUser = 'overtrue_test_xxxx';
$apiKey = 'UWoBGa2sgxyxxxxxxxx';

$client = new SendCloud($apiUser, $apiKey);

$result = $client->post('mail/send', [
    'from' => 'overtrue@domain.sendcloud.org',
    'to' => 'demo@easywechat.com',
    'subject' => '来自 SendCloud 的第一封邮件！',
    'html' => '你太棒了！你已成功的 从 SendCloud 发送了一封测试邮件！',
]);

var_dump($result);

//{
//    "result": true,
//    "statusCode": 200,
//    "message": "请求成功",
//    "info": {
//        "emailIdList": [
//            "1513828329529_91891_27315_500.sc-10_9_13_218-inbounddemo@easywechat.com"
//        ]
//    }
//}⏎

```

## Requirements and compatibility

Version 2 requires PHP 7.2.5+ (PHP 7 or 8), Guzzle 7.15.2+, and
`overtrue/http` 1.2.3+. Use a currently supported PHP release for production.
The PHP minimum and Guzzle 7 requirement are breaking changes from version 1.

Requests use `https://api.sendcloud.net/apiv2/` by default. Endpoint paths such
as `mail/send` and `/mail/send` are resolved under this base path. An explicitly
configured `base_uri` is honored; include its trailing slash. Absolute URLs
retain Guzzle's normal behavior. Only use trusted endpoints with your API keys.

```php
$client = new SendCloud($apiUser, $apiKey, [
    'base_uri' => 'https://api.sendcloud.net/apiv2/',
    'response_type' => 'array',
]);
```

POST form requests carry authentication in the form body. Use `upload()` for
attachments; authentication is added as multipart fields without URL-encoding
those field values. GET authentication is added to the query without removing
other parameters. Non-form request bodies are left unchanged and authentication
is added to their query. Response types and exception behavior remain those of
`overtrue/http`; API-level failures are not converted into exceptions.

```php
$result = $client->upload('mail/send', [
    'attachments' => '/path/to/report.pdf',
], [
    'from' => 'sender@example.com',
    'to' => 'recipient@example.com',
    'subject' => 'Report',
    'html' => 'Please see the attachment.',
]);

$result = $client->postAsync('mail/send', $message)->wait();
```

See SendCloud's [delivery contract](https://www.sendcloud.net/doc/en/email_v2/send_email/)
and [GET API contract](https://www.sendcloud.net/doc/en/email_v2/apiuser_do/).

## Tests

```shell
composer install
composer validate --strict
composer test
composer lint
composer audit
```

The dependency-free regression runner uses Guzzle's `MockHandler` and synthetic
credentials. It retains the real authentication middleware and sends no email or
live API requests. CI tests lowest and latest installable dependencies without
disabling Composer's advisory checks.

## License

MIT
