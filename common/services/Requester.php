<?php declare(strict_types=1);

namespace common\services;

use Assert\Assertion;
use common\components\guzzle\Guzzle;
use common\services\feeds\RssService;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\RequestOptions;

class Requester
{
    private Guzzle $guzzle;

    public function __construct(Guzzle $guzzle)
    {
        $this->guzzle = $guzzle;
    }

    /**
     * @param Request $request
     * @param array $options
     * @return PromiseInterface
     */
    public function sendAsyncRequest(Request $request, $options = []): PromiseInterface
    {
        Assertion::url((string)$request->getUri());

        return $this->guzzle->sendAsync($request, $options);
    }

    /**
     * @param Request $request
     * @param array $options
     * @return PromiseInterface
     */
    public function sendAsyncRequestWithProxy(Request $request, $options = []): PromiseInterface
    {
        Assertion::url((string)$request->getUri());

        if (!isset($options[Guzzle::PROXY_ENABLING_ATTEMPT]) || $options[Guzzle::PROXY_ENABLING_ATTEMPT] === 0) {
            $this->guzzle->addProxyOptions($options, $request);
            if (isset($options[RequestOptions::HEADERS]['User-Agent'])) {
                $userAgent = $options[RequestOptions::HEADERS]['User-Agent'];
                unset ($options[RequestOptions::HEADERS]['User-Agent']);
                $options[RequestOptions::HEADERS]['X-Proxy-User-Agent'] = $userAgent;
            } else if ($userAgent = RssService::getUserAgent((string)$request->getUri())) {
                $options[RequestOptions::HEADERS]['X-Proxy-User-Agent'] = $userAgent;
            }
        }

        return $this->sendAsyncRequest($request, $options);
    }


    public function setDebug($debug = true): void
    {
        $this->guzzle->debug = $debug;
    }
}