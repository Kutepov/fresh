<?php declare(strict_types=1);

namespace common\services\apps;

use common\models\App;
use common\services\apps\entities\SplitmetricsAcquireEvent;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

class SplitmetricsAcquire
{
    private const URL = 'https://events.searchadshq.com/api/events/HytDmsiPTabGkttJvF7qzB/custom';
    private Client $guzzle;

    public function __construct(Client $guzzle)
    {
        $this->guzzle = $guzzle;
    }

    public function sendAppInstallEvent(App $app, &$debug = null)
    {
        if ($event = SplitmetricsAcquireEvent::createForApp($app, null, $debug)) {
            $this->sendEvent($event, $debug);
        }

    }

    public function sendEvent(SplitmetricsAcquireEvent $event, &$debug = null, $logKey = 'qonversion'): void
    {
        $debug['preparedEvent'] = $event->getWebhookQueryParams();

        try {
            $response = $this->guzzle->get(self::URL, [
                RequestOptions::QUERY => $event->getWebhookQueryParams(),
                RequestOptions::HTTP_ERRORS => true
            ]);
            $debug['splitmetricsResponse'] = $response->getBody()->getContents();
        } catch (\Throwable $e) {
            $debug['splitmetricsError'] = $e->getCode() . ': ' . $e->getMessage();
        }

        \Yii::info($debug, $logKey . '-events');
    }
}