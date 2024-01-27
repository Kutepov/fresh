<?php namespace common\components\guzzle;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class RedirectMiddleware extends \GuzzleHttp\RedirectMiddleware
{
    public function modifyRequest(RequestInterface $request, array $options, ResponseInterface $response): RequestInterface
    {
        $request = parent::modifyRequest($request, $options, $response);

        if ($response->hasHeader('Proxy-Addr') && $request->getUri()->getScheme() === 'https') {
            $request = $request
                ->withUri($request->getUri()->withScheme('http'))
                ->withHeader('X-Original-Scheme', 'https');
        }

        return $request;
    }
}