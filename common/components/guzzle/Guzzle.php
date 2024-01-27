<?php namespace common\components\guzzle;

use Doctrine\Common\Cache\PredisCache;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\RequestOptions;
use Kevinrob\GuzzleCache\Storage\DoctrineCacheStorage;
use Kevinrob\GuzzleCache\Strategy\Delegate\DelegatingCacheStrategy;
use Kevinrob\GuzzleCache\Strategy\GreedyCacheStrategy;
use Kevinrob\GuzzleCache\Strategy\NullCacheStrategy;
use Predis\Client;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use yii\helpers\Json;
use Kevinrob\GuzzleCache\CacheMiddleware;

class Guzzle extends \GuzzleHttp\Client
{
    public $debug = false;

    public const PROXY_ALWAYS_ENABLED = 0;
    public const ENABLE_PROXY_AFTER_FIRST_ERROR = 1;

    public const PROXY_ENABLING_ATTEMPT = 'proxy_enabling_attempt';

    public function __construct(array $config = [])
    {
        $stack = HandlerStack::create();

        $retryAttemptsCount = 3;

        $stack->remove(RequestOptions::ALLOW_REDIRECTS);
        $stack->before(RequestOptions::COOKIES, function (callable $handler) {
            return new RedirectMiddleware($handler);
        }, RequestOptions::ALLOW_REDIRECTS);

        $stack->before(RequestOptions::ALLOW_REDIRECTS, GuzzleRetryMiddleware::factory([
            'retry_on_timeout' => true,
            'default_retry_multiplier' => 0,
            'retry_on_status' => [500, 502, 503, 506, 403, 400, 429],
            'max_retry_attempts' => $retryAttemptsCount,
            'on_retry_callback' => function ($attemptNumber, $delay, RequestInterface &$request, &$options, ResponseInterface $response = null, $reason) use ($retryAttemptsCount) {
                $sourceId = $options['source_id'] ?? 0;

                $proxyEnablingAttemptCondition = $options[self::PROXY_ENABLING_ATTEMPT];

                if ($this->debug) {
                    if (isset($options['proxy']) && !is_null($options['proxy'])) {
                        echo date('[H:i:s]') . ' [' . $sourceId . '] Request error with proxy ' . $options['proxy'] . PHP_EOL;
                    }
                    else {
                        echo date('[H:i:s]') . ' [' . $sourceId . '] Request error' . PHP_EOL;
                    }
                }

                if (!$options['proxy'] && $attemptNumber >= $proxyEnablingAttemptCondition && !is_null($proxyEnablingAttemptCondition)) {
                    $this->addProxyOptions($options, $request);
                }
                /** На случай, если балансер упадет */
                elseif ($options['proxy']) {
                    $this->removeProxyOptions($options, $request);
                }

                if ($this->debug) {
                    echo 'attempt #' . $attemptNumber . PHP_EOL;
                    echo date('[H:i:s]') . ' [' . $sourceId . '] ' . ($response ? $response->getStatusCode() . ':' : '') . ' ' . ($reason ? get_class($reason) . ': ' . $reason->getMessage() : '') . PHP_EOL;
                    echo date('[H:i:s]') . ' [' . $sourceId . '] [' . $options['track_number'] . '] ' . $request->getMethod() . ' ' . $request->getUri() . ' ' . Json::encode($request->getBody()->getContents()) . PHP_EOL;
                    if (isset($options['proxy']) && !is_null($options['proxy'])) {
                        echo date('[H:i:s]') . ' [' . $sourceId . '] retry with proxy ' . $options['proxy'] . '...' . PHP_EOL;
                    }
                }
            }]), 'retry');

        if (!defined('CONSOLE_DEBUG')) {
            $cacheStrategy = new DelegatingCacheStrategy(new NullCacheStrategy());
            /** @var ArticlePageRequestMatcher $requestMatcher */
            $requestMatcher = \Yii::$container->get(ArticlePageRequestMatcher::class);
            $cacheStrategy->registerRequestMatcher($requestMatcher, new GreedyCacheStrategy(
                new DoctrineCacheStorage(
                    new PredisCache(
                        new Client('tcp://redis:6379')
                    )
                ),
                900
            ));

            $stack->push(new CacheMiddleware(
                $cacheStrategy
            ), 'cache');
        }

        parent::__construct([
            'handler' => $stack,
            RequestOptions::ALLOW_REDIRECTS => [
                'max' => 6,
                'track_redirects' => true
            ],
            RequestOptions::HEADERS => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.90 Safari/537.36',
                'Accept-Encoding' => 'gzip, deflate',
            ],
            RequestOptions::CONNECT_TIMEOUT => 5,
            RequestOptions::TIMEOUT => 30,
            RequestOptions::READ_TIMEOUT => 30,
            RequestOptions::VERIFY => false,
            RequestOptions::DEBUG => $this->debug,
            RequestOptions::HTTP_ERRORS => false,
//            'curl' => [CURLOPT_VERBOSE => true]
        ]);
    }

    private function removeProxyOptions(&$options, RequestInterface &$request): void
    {
        unset ($options[RequestOptions::PROXY]);
        $options[RequestOptions::CONNECT_TIMEOUT] = 5;
        $options[RequestOptions::TIMEOUT] = 30;
        $options[RequestOptions::READ_TIMEOUT] = 30;
        $options[self::PROXY_ENABLING_ATTEMPT] = null;

        if ($request->hasHeader('X-Original-Scheme')) {
            $request = $request
                ->withUri($request->getUri()->withScheme('https'))
                ->withoutHeader('X-Original-Scheme');
        }
    }

    public function addProxyOptions(&$options, RequestInterface &$request): void
    {
        $options[RequestOptions::PROXY] = env('PROXY_ADDRESS');
        if ($request->getUri()->getScheme() === 'https') {
            $request = $request
                ->withUri($request->getUri()->withScheme('http'))
                ->withHeader('X-Original-Scheme', 'https');
        }
        $options[RequestOptions::CONNECT_TIMEOUT] = 60;
        $options[RequestOptions::TIMEOUT] = 60;
        $options[RequestOptions::READ_TIMEOUT] = 60;
    }
}