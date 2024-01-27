<?php namespace common\services;

use common\components\guzzle\Guzzle;
use common\models\Proxy;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Each;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;

class ProxyService
{
    public $checkerUrl;
    public $accounts = [];
    private $apiUrl = 'https://buy.fineproxy.org/api/getproxy/';

    private $guzzle;

    public function __construct(Guzzle $guzzle)
    {
        $this->guzzle = new Client([
            'curl' => [
                CURLOPT_FORBID_REUSE => true,
                CURLOPT_FRESH_CONNECT => true,
            ]
        ]);
    }

    public function checkCountry(Proxy $proxy): PromiseInterface
    {
        return $this->guzzle
            ->getAsync($this->checkerUrl, [
                'retry_enabled' => false,
                RequestOptions::CONNECT_TIMEOUT => 10,
                RequestOptions::TIMEOUT => 10,
                RequestOptions::PROXY => $proxy->address
            ])
            ->then(static function (ResponseInterface $response) use (&$proxy) {
                $response = json_decode($response->getBody()->getContents());

                $proxy->updateAttributes([
                    'country' => $response->result
                ]);

                return $response->result;
            });
    }

    private function getProxyList(): array
    {
        $result = [];

        foreach ($this->accounts as $credentials) {
            [$login, $password] = explode('@', $credentials);

            $response = $this->guzzle->get($this->apiUrl, [
                RequestOptions::HEADERS => [
                    'cache-control' => 'no-cache',
                    'pragma' => 'no-cache',
                    'accept-encoding' => 'gzip, deflate',
                    'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.4896.127 Safari/537.36'
                ],
                RequestOptions::QUERY => [
                    'format' => 'txt',
                    'type' => 'http_ip',
                    'login' => $login,
                    'password' => $password
                ],
                'curl' =>[
                    CURLOPT_VERBOSE => true,
                    CURLOPT_FORBID_REUSE => true,
                    CURLOPT_FRESH_CONNECT => true
                ]
            ]);

            if (isset($response->getHeaders()['Warning'])) {
                echo $login . PHP_EOL . PHP_EOL;
                dd($response->getHeaders());
                dd($response->getBody()->getContents());
            }

            $list = explode("\n", trim($response->getBody()->getContents()));
            $list = array_map('trim', $list);

            $result[$login] = $list;
        }

        return $result;
    }

    public function checkCountryOfAll(): void
    {
        $generator = function () {
            $proxies = Proxy::find()->where(['country' => null])->all();
            foreach ($proxies as $proxy) {
                yield $this->checkCountry($proxy);
            }
        };

        Each::ofLimit($generator(), 16)->wait();
    }

    public function updateProxies(): void
    {
        $proxies = $this->getProxyList();

        Proxy::deleteAll(['IS', 'account', null]);

        foreach ($proxies as $account => $proxyList) {
            if (count($proxyList)) {
                \common\models\Proxy::deleteAll([
                    'AND',
                    ['NOT IN', 'address', $proxyList],
                    ['=', 'account', $account]
                ]);

                foreach ($proxyList as $address) {
                    if (!Proxy::find()->where(['address' => $address])->exists()) {
                        (new \common\models\Proxy([
                            'address' => $address,
                            'account' => $account,
                            'passed'=> 1
                        ]))->save();
                    }
                    else {
                        Proxy::updateAll([
                            'passed' => 1
                        ], [
                            'address' => $address
                        ]);
                    }
                }
            }
        }
    }

    /**
     * @return Proxy[]
     */
    public function getAll(): array
    {
        return Proxy::find()
            ->select(['address', 'account'])
            ->orderBy('address')
            ->andWhere(['passed' => 1])
            ->all();
    }

    public function getSubnetPoolsList(): array
    {
        $pools = [];
        $proxies = $this->getAll();

        foreach ($proxies as $proxy) {
            [$one, $two, $three] = explode('.', $proxy->address);
            $subnet = implode('_', [$one, $two, $three]);
            $pools[$proxy->account . '_' . $subnet][] = $proxy->address;
        }

        return $pools;
    }
}