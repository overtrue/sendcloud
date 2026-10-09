<?php

// Warnings and notices fail the suite; upstream deprecations remain visible.
set_error_handler(function ($severity, $message, $file, $line) {
    if ($severity & (E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE)) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
    return false;
});

require __DIR__.'/../vendor/autoload.php';

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Overtrue\Http\Support\Collection;
use Overtrue\SendCloud\SendCloud;
use Psr\Http\Message\ResponseInterface;

// All requests terminate in MockHandler. These synthetic values are not API keys.
const API_USER = 'test +&=用户';
const API_KEY = 'synthetic +&=%/?密钥';

function same($expected, $actual)
{
    if ($expected !== $actual) {
        throw new RuntimeException('Expected '.var_export($expected, true).', got '.var_export($actual, true));
    }
}

function check($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function client(&$history, array $config = [], array $responses = [])
{
    $history = [];
    $client = new SendCloud(API_USER, API_KEY, $config);
    // Replace only the transport, retaining the SDK authentication middleware.
    $client->getHandlerStack()->setHandler(new MockHandler($responses ?: [new Response(200, [], '{"result":true}')]));
    $client->getHandlerStack()->push(Middleware::history($history));

    return $client;
}

function form($request)
{
    parse_str((string) $request->getBody(), $form);
    return $form;
}

function auth(array $parameters)
{
    same(API_USER, $parameters['apiUser']);
    same(API_KEY, $parameters['apiKey']);
}

function lengthMatches($request)
{
    same((string) strlen((string) $request->getBody()), $request->getHeaderLine('Content-Length'));
}

$tests = [];
$tests['HTTPS, documented path and POST body authentication'] = function () {
    $c = client($history);
    same(['result' => true], $c->post('/mail/send?trace=one', ['subject' => '你好 +&= 100%', 'html' => '<b>hello</b>']));
    $r = $history[0]['request'];
    same('https://api.sendcloud.net/apiv2/mail/send?trace=one', (string) $r->getUri());
    same('POST', $r->getMethod());
    auth(form($r));
    same('你好 +&= 100%', form($r)['subject']);
    same('<b>hello</b>', form($r)['html']);
    same('application/x-www-form-urlencoded', $r->getHeaderLine('Content-Type'));
    lengthMatches($r);
};
$tests['relative paths and custom base URI'] = function () {
    $c = client($history, ['base_uri' => 'https://example.test/custom/']);
    $c->post('mail/send', []);
    same('https://example.test/custom/mail/send', (string) $history[0]['request']->getUri());
    auth(form($history[0]['request']));
};
$tests['absolute URLs retain Guzzle resolution'] = function () {
    $c = client($history);
    $c->get('https://example.test/exact?x=1');
    same('example.test', $history[0]['request']->getUri()->getHost());
    same('/exact', $history[0]['request']->getUri()->getPath());
};
$tests['GET preserves repeated and encoded parameters'] = function () {
    $c = client($history);
    $c->get('/label/list?x=1&x=2&value=a%2Bb%20c&apiUser=wrong&apiKey=wrong');
    $q = $history[0]['request']->getUri()->getQuery();
    check(0 === strpos($q, 'x=1&x=2&value=a%2Bb%20c&'), 'Query bytes changed');
    parse_str($q, $query);
    auth($query);
    same(1, substr_count($q, 'apiUser='));
    same(1, substr_count($q, 'apiKey='));
};
$tests['Guzzle query option retains data'] = function () {
    $c = client($history);
    $c->get('label/list', ['query' => ['labelId' => 42, 'name' => '测试 +']]);
    parse_str($history[0]['request']->getUri()->getQuery(), $query);
    auth($query);
    same('42', $query['labelId']);
    same('测试 +', $query['name']);
};
$tests['form credentials replaced without losing duplicate values'] = function () {
    $c = client($history);
    $c->request('mail/send', 'post', ['body' => 'x=1&x=2&api%55ser=wrong&apiKey=wrong', 'headers' => ['Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8']]);
    $r = $history[0]['request'];
    check(0 === strpos((string) $r->getBody(), 'x=1&x=2&'), 'Duplicate form values lost');
    auth(form($r));
    same(1, substr_count((string) $r->getBody(), 'apiUser='));
    lengthMatches($r);
};
$tests['empty POST becomes authenticated form'] = function () {
    $c = client($history);
    $c->requestRaw('mail/send', 'POST');
    auth(form($history[0]['request']));
    same('', $history[0]['request']->getUri()->getQuery());
};
$tests['multipart attachment and credentials use one valid boundary'] = function () {
    $c = client($history);
    $file = fopen('php://temp', 'r+');
    fwrite($file, "binary\0attachment\r\n你好");
    rewind($file);
    $c->upload('/mail/send?trace=1', ['attachments' => $file], ['subject' => '你好', 'apiUser' => 'wrong', 'apiKey' => 'wrong']);
    $r = $history[0]['request'];
    $body = (string) $r->getBody();
    preg_match('/boundary=(.*)/', $r->getHeaderLine('Content-Type'), $match);
    $boundary = $match[1];
    same(1, substr_count($body, '--'.$boundary."--\r\n"));
    same(1, substr_count($body, 'name="apiUser"'));
    same(1, substr_count($body, 'name="apiKey"'));
    check(false !== strpos($body, "\r\n\r\n".API_USER."\r\n"), 'Missing raw multipart user');
    check(false !== strpos($body, "\r\n\r\n".API_KEY."\r\n"), 'Missing raw multipart key');
    check(false !== strpos($body, "binary\0attachment\r\n你好"), 'Attachment changed');
    same('trace=1', $r->getUri()->getQuery());
    lengthMatches($r);
    fclose($file);
};
$tests['JSON remains unchanged, authentication preserves query'] = function () {
    $c = client($history);
    $c->request('custom?trace=1', 'POST', ['json' => ['value' => '你好']]);
    $r = $history[0]['request'];
    same(['value' => '你好'], json_decode((string) $r->getBody(), true));
    parse_str($r->getUri()->getQuery(), $q);
    auth($q);
    same('1', $q['trace']);
};
$tests['repeated calls never accumulate authentication or lose payload'] = function () {
    $c = client($history, [], [new Response(200, [], '{}'), new Response(200, [], '{}')]);
    for ($i = 0; $i < 2; ++$i) {
        $c->post('/mail/send', ['subject' => 'repeat']);
        $r = $history[$i]['request'];
        auth(form($r));
        same('repeat', form($r)['subject']);
        same(1, substr_count((string) $r->getBody(), 'apiKey='));
    }
    same((string) $history[0]['request']->getBody(), (string) $history[1]['request']->getBody());
};
foreach (['array', 'object', 'collection', 'raw'] as $type) {
    $tests['response type '.$type.' remains unchanged'] = function () use ($type) {
        $c = client($history, ['response_type' => $type]);
        $result = $c->post('mail/send');
        if ('array' === $type) {
            same(['result' => true], $result);
        } elseif ('object' === $type) {
            same(true, $result->result);
        } elseif ('collection' === $type) {
            check($result instanceof Collection, 'Not a collection');
            same(true, $result['result']);
        } else {
            check($result instanceof ResponseInterface, 'Not a raw response');
            same('{"result":true}', (string) $result->getBody());
        }
    };
}
$tests['API-level failure stays a response'] = function () {
    $payload = ['result' => false, 'statusCode' => 40001, 'message' => 'synthetic failure'];
    $c = client($history, [], [new Response(200, [], json_encode($payload))]);
    same($payload, $c->post('mail/send'));
};
$tests['HTTP failure propagates'] = function () {
    $c = client($history, [], [new Response(400, [], '{"result":false}')]);
    try {
        $c->post('mail/send');
    } catch (ClientException $e) {
        same(400, $e->getResponse()->getStatusCode());
        return;
    }
    throw new RuntimeException('Expected ClientException');
};
$tests['http_errors option remains configurable'] = function () {
    $c = client($history, [], [new Response(400, [], '{"result":false}')]);
    same(['result' => false], $c->post('mail/send', [], ['http_errors' => false]));
};
$tests['transport errors propagate'] = function () {
    $error = new ConnectException('synthetic connection failure', new Request('POST', 'https://example.test/'));
    $c = client($history, [], [$error]);
    try {
        $c->post('mail/send');
    } catch (ConnectException $e) {
        same($error, $e);
        return;
    }
    throw new RuntimeException('Expected ConnectException');
};
$tests['async POST returns promise and authenticates'] = function () {
    $c = client($history);
    $promise = $c->postAsync('/mail/send', ['subject' => 'async']);
    check($promise instanceof PromiseInterface, 'Not a promise');
    same(['result' => true], $promise->wait());
    auth(form($history[0]['request']));
};
$tests['async GET and raw response'] = function () {
    $c = client($history, ['response_type' => 'raw']);
    $result = $c->getAsync('/label/list?labelId=1')->wait();
    check($result instanceof ResponseInterface, 'Not a raw response');
    parse_str($history[0]['request']->getUri()->getQuery(), $q);
    auth($q);
    same('1', $q['labelId']);
};
$tests['async HTTP errors reject'] = function () {
    $c = client($history, [], [new Response(400)]);
    try {
        $c->postAsync('mail/send')->wait();
    } catch (ClientException $e) {
        same(400, $e->getResponse()->getStatusCode());
        return;
    }
    throw new RuntimeException('Expected async rejection');
};

$tests['async multipart preserves form and file bytes'] = function () {
    $c = client($history);
    $file = fopen('php://temp', 'r+');
    fwrite($file, 'async attachment');
    rewind($file);
    same(['result' => true], $c->uploadAsync('mail/send', ['attachments' => $file], ['subject' => 'async'])->wait());
    $r = $history[0]['request'];
    check(false !== strpos((string) $r->getBody(), 'async attachment'), 'Async file missing');
    check(false !== strpos((string) $r->getBody(), API_KEY), 'Async multipart authentication missing');
    same('', $r->getUri()->getQuery());
    lengthMatches($r);
    fclose($file);
};
$tests['partially read form streams retain the full encoded body'] = function () {
    $c = client($history);
    $stream = new GuzzleHttp\Psr7\Stream(fopen('php://temp', 'r+'));
    $stream->write('subject=hello&x=1');
    $stream->rewind();
    $stream->read(5);
    $c->request('mail/send', 'POST', ['body' => $stream, 'headers' => ['Content-Type' => 'application/x-www-form-urlencoded']]);
    $r = $history[0]['request'];
    auth(form($r));
    same('hello', form($r)['subject']);
    same('1', form($r)['x']);
    lengthMatches($r);
};

$failures = 0;
foreach ($tests as $name => $test) {
    try {
        $test();
        echo "PASS: $name\n";
    } catch (Throwable $e) {
        ++$failures;
        fwrite(STDERR, "FAIL: $name\n".$e."\n");
    }
}
echo count($tests).' tests, '.$failures." failures\n";
exit($failures ? 1 : 0);
