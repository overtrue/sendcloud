<?php

/*
 * This file is part of the overtrue/sendcloud.
 *
 * (c) overtrue <anzhengchao@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Overtrue\SendCloud;

use Overtrue\Http\Client;
use Overtrue\Http\Config;
use Psr\Http\Message\RequestInterface;
use function GuzzleHttp\Psr7\modify_request;

/**
 * Class SendCloud.
 *
 * @author overtrue <i@overtrue.me>
 */
class SendCloud extends Client
{
    const BASE_URI = 'http://api.sendcloud.net/apiv2/';

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

        $config['base_uri'] = self::BASE_URI;

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
                $credential = http_build_query([
                    'apiUser' => $this->apiUser,
                    'apiKey' => $this->apiKey,
                ]);

                if ('post' == $request->getMethod()) {
                    $request = modify_request($request, ['body' => $request->getBody()->getContents().'&'.$credential]);
                } else {
                    $request = $request->withUri($request->getUri()->withQuery($credential));
                }

                return $handler($request, $options);
            };
        };
    }
}
