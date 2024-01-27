<?php namespace console\controllers;

use common\components\guzzle\Guzzle;
use common\models\Proxy;
use common\models\UserAgent;
use common\services\ProxyService;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Promise\Each;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use yii\console\ExitCode;
use yii\db\Query;
use yii\helpers\Json;
use function GuzzleHttp\Promise\each_limit;

class ProxyController extends Controller
{
    /** @var ProxyService */
    private $service;

    /** @var Guzzle */
    private $guzzle;

    public function __construct($id, $module, ProxyService $service, Guzzle $guzzle, $config = [])
    {
        $this->guzzle = $guzzle;
        $this->service = $service;
        parent::__construct($id, $module, $config);
    }

    public function actionUpdate(): void
    {
        $this->service->updateProxies();
    }

    public function actionCheckCountries(): void
    {
        $this->service->checkCountryOfAll();
    }

    public function actionCheck()
    {
        $proxies = Proxy::find()->all();
        $requests = [];
        foreach ($proxies as $proxy) {
            $requests[] = $this->guzzle->sendAsync(new Request('GET', 'http://httpbin.org/ip'), [
                RequestOptions::PROXY => '62.149.15.91:8088',
                RequestOptions::HTTP_ERRORS => true,
                RequestOptions::TIMEOUT => 60,
                RequestOptions::CONNECT_TIMEOUT => 60,
                RequestOptions::READ_TIMEOUT => 60,
                RequestOptions::HEADERS => [
                    'Proxy-Addr' => $proxy->address
                ]
            ])->then(function (ResponseInterface $response) use ($proxy, &$i) {
                echo $proxy->address . ": " . $response->getBody()->getContents() . PHP_EOL;
                Proxy::updateAll([
                    'passed' => 1
                ], ['address' => $proxy->address]);
                echo ++$i . "\n";
            })->otherwise(function ($e) use ($proxy, &$i) {
                echo $proxy->address . ' - ' . $e->getMessage() . PHP_EOL;
                Proxy::updateAll([
                    'passed' => 0
                ], ['address' => $proxy->address]);

                echo ++$i . "\n";
            });
            break;
        }

        Each::ofLimit($requests, 5)->wait();

    }

    public function actionUpdateBalancer(): int
    {
        $pools = $this->service->getSubnetPoolsList();

        $body = [
            'rules' => [
                [
                    'domainName' => 'www.ontrac.com',
                    'allowedPools' => []
                ],
                [
                    'domainName' => 'gonderitakip.ptt.gov.tr',
                    'allowedPools' => ['proxy6']
                ],
//                [
//                    'domainName' => 'www.mondialrelay.fr',
//                    'allowedPools' => []
//                ]
            ],
            'pools' => [],
            'userAgents' => (new Query())->from('user_agents')->select('useragent')->column()
        ];
        $pools['proxy6'] = [
'socks5://MCN9sj:VwUJVt@138.219.75.101:9148',
'socks5://MCN9sj:VwUJVt@138.219.75.242:9781',
'socks5://MCN9sj:VwUJVt@138.219.120.248:9872',
'socks5://MCN9sj:VwUJVt@170.244.95.48:9351',
'socks5://MCN9sj:VwUJVt@138.219.120.40:9633',
'socks5://MCN9sj:VwUJVt@138.219.75.130:9792',
'socks5://MCN9sj:VwUJVt@138.219.120.138:9930',
'socks5://MCN9sj:VwUJVt@170.244.95.121:9874',
'socks5://MCN9sj:VwUJVt@138.219.120.237:9859',
'socks5://MCN9sj:VwUJVt@191.102.176.149:9684',
'socks5://MCN9sj:VwUJVt@191.102.176.122:9846',
'socks5://MCN9sj:VwUJVt@191.102.176.235:9750',
'socks5://MCN9sj:VwUJVt@138.219.75.174:9751',
'socks5://MCN9sj:VwUJVt@191.102.176.90:9860',
'socks5://MCN9sj:VwUJVt@138.219.120.227:9861',
'socks5://MCN9sj:VwUJVt@191.102.176.204:9194',
'socks5://MCN9sj:VwUJVt@138.219.75.77:9342',
'socks5://MCN9sj:VwUJVt@138.219.75.190:9693',
'socks5://MCN9sj:VwUJVt@138.219.120.56:9936',
'socks5://MCN9sj:VwUJVt@170.244.95.123:9091',
'socks5://MCN9sj:VwUJVt@191.102.176.208:9941',
'socks5://MCN9sj:VwUJVt@191.102.176.100:9299',
'socks5://MCN9sj:VwUJVt@170.244.95.98:9137',
'socks5://MCN9sj:VwUJVt@191.102.176.18:9745',
'socks5://MCN9sj:VwUJVt@138.219.75.17:9779',
'socks5://MCN9sj:VwUJVt@191.102.176.19:9118',
'socks5://MCN9sj:VwUJVt@191.102.176.13:9506',
'socks5://MCN9sj:VwUJVt@170.244.95.89:9163',
'socks5://MCN9sj:VwUJVt@138.219.120.4:9230',
'socks5://MCN9sj:VwUJVt@191.102.176.88:9452'
        ];

        foreach ($pools as $poolName => $pool) {

            [$login, $one, $two, $three] = explode('_', $poolName);
            $subnet = $one . '.' . $two . '.' . $three;


            [$accountName, $first, $second] = explode('_', $poolName);

            if ($accountName === 'US368651') {
                $body['rules'][0]['allowedPools'][] = $poolName;
            } elseif ($accountName === 'FR402537' && ($first == 194 && $second == 99)) {
                //$body['rules'][1]['allowedPools'][] = $poolName;
            }

            $body['pools'][] = [
                'name' => $poolName,
                'maxConnections' => 90,
                'proxies' => $pool
            ];
        }


        $this->stdout($this->guzzle->post('http://62.149.15.91:8088/_up', [
            RequestOptions::FORM_PARAMS => [
                'key' => 'xxx',
                'data' => Json::encode((object)$body)
            ]
        ])->getBody()->getContents());

        return ExitCode::OK;
    }
}