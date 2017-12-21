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

$result = $client->post('/mail/send', [
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

## License

MIT