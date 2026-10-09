<?php

/*
 * This file is part of the overtrue/sendcloud.
 *
 * (c) overtrue <anzhengchao@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Overtrue\SendCloud;

use GuzzleHttp\Psr7\AppendStream;
use GuzzleHttp\Psr7\LimitStream;
use GuzzleHttp\Psr7\MultipartStream;
use GuzzleHttp\Psr7\Stream;
use Overtrue\Http\Client;
use Overtrue\Http\Config;
use Psr\Http\Message\RequestInterface;

/**
 * Class SendCloud.
 *
 * @author overtrue <i@overtrue.me>
 */
class SendCloud extends Client
{
    const BASE_URI = 'https://api.sendcloud.net/apiv2/';

    /**
     * @var string
     */
    protected $apiUser;

    /**
     * @var string
     */
    protected $apiKey;

    /**
     * SendCloud constructor.
     *
     * @param string $apiUser
     * @param string $apiKey
     * @param array  $config
     */
    public function __construct(string $apiUser, string $apiKey, array $config = [])
    {
        $this->apiUser = $apiUser;
        $this->apiKey = $apiKey;

        $config += ['base_uri' => self::BASE_URI];

        parent::__construct(new Config($config));

        $this->pushMiddleware($this->authorizationParamsMiddleware(), 'auth');
    }

    /**
     * @return \Closure
     */
    public function authorizationParamsMiddleware()
    {
        return function (callable $handler) {
            return function (RequestInterface $request, array $options) use ($handler) {
                $credentials = [
                    'apiUser' => $this->apiUser,
                    'apiKey' => $this->apiKey,
                ];
                $contentType = strtolower(explode(';', $request->getHeaderLine('Content-Type'))[0]);
                $body = $request->getBody();

                if ('POST' === strtoupper($request->getMethod()) && $body instanceof MultipartStream) {
                    $parts = [];
                    foreach ($credentials as $name => $contents) {
                        $parts[] = compact('name', 'contents');
                    }
                    $auth = new MultipartStream($parts, $body->getBoundary());
                    // Keep the original multipart stream and its closing boundary intact.
                    $prefix = new LimitStream($auth, $auth->getSize() - strlen('--'.$body->getBoundary()."--\r\n"));
                    $request = $request->withBody(new AppendStream([$prefix, $body]));
                    $request = $this->withBodyLength($request);
                } elseif ('POST' === strtoupper($request->getMethod())
                    && ('application/x-www-form-urlencoded' === $contentType || ('' === $contentType && 0 === $body->getSize()))) {
                    $stream = new Stream(fopen('php://temp', 'r+'));
                    $stream->write($this->withCredentials((string) $body, $credentials));
                    $stream->rewind();
                    $request = $request->withBody($stream)->withHeader('Content-Type', 'application/x-www-form-urlencoded');
                    $request = $this->withBodyLength($request);
                } else {
                    // Leave non-form bodies untouched, preserving unrelated query parameters.
                    $request = $request->withUri($request->getUri()->withQuery(
                        $this->withCredentials($request->getUri()->getQuery(), $credentials)
                    ));
                }

                return $handler($request, $options);
            };
        };
    }

    /**
     * Treat API endpoint paths with a leading slash like their relative form.
     */
    public function requestRaw(string $uri, string $method = 'GET', array $options = [], bool $async = false)
    {
        if (isset($uri[0]) && '/' === $uri[0] && substr($uri, 0, 2) !== '//') {
            $uri = ltrim($uri, '/');
        }

        if ('POST' === strtoupper($method) && isset($options['multipart'])) {
            $options['multipart'] = array_values(array_filter($options['multipart'], function ($part) {
                return !isset($part['name']) || !in_array($part['name'], ['apiUser', 'apiKey'], true);
            }));
        }

        return parent::requestRaw($uri, $method, $options, $async);
    }

    /**
     * Preserve encoded values and repeated parameters while replacing credentials.
     */
    protected function withCredentials(string $query, array $credentials): string
    {
        $parameters = array_filter(explode('&', $query), function ($parameter) use ($credentials) {
            return '' !== $parameter && !array_key_exists(urldecode(explode('=', $parameter, 2)[0]), $credentials);
        });
        $parameters[] = http_build_query($credentials, '', '&', PHP_QUERY_RFC3986);

        return implode('&', $parameters);
    }

    protected function withBodyLength(RequestInterface $request): RequestInterface
    {
        $request = $request->withoutHeader('Content-Length');
        $size = $request->getBody()->getSize();

        if (null !== $size && !$request->hasHeader('Transfer-Encoding')) {
            $request = $request->withHeader('Content-Length', (string) $size);
        }

        return $request;
    }
}
