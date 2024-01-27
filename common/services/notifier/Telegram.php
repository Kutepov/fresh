<?php namespace common\services\notifier;

use common\components\guzzle\Guzzle;
use common\contracts\Logger;
use common\contracts\Notifier;
use GuzzleHttp\RequestOptions;
use yii\base\BaseObject;
use yii\helpers\Json;

class Telegram extends BaseObject implements Notifier
{
    private $guzzle;
    private $logger;
    private $apiUrl = 'https://api.telegram.org/';

    public $botApiToken;
    public $groupId;
    public $adaptyIosGroupId;
    public $adaptyAndroidGroupId;

    public function __construct(Guzzle $guzzle, Logger $logger, $config = [])
    {
        $this->guzzle = $guzzle;
        $this->logger = $logger;

        parent::__construct($config);
    }

    public function sendNotification(Notification $notification): void
    {
        try {
            $response = $this->guzzle->get($this->apiUrl . 'bot' . $this->botApiToken . '/sendMessage', [
                RequestOptions::QUERY => [
                    'chat_id' => $this->getGroupIdForNotification($notification),
                    'text' => $notification->getNotificationBody(),
                    'parse_mode' => 'html',
                    'disable_notification' => $notification->isMuted(),
                    'disable_web_page_preview' => !empty($notification->getAdaptyPlatform())
                ]
            ]);
            $response = Json::decode($response->getBody()->getContents());
        } catch (\Throwable $e) {
            $this->logger->critical($e);
        }
    }

    private function getGroupIdForNotification(Notification $notification)
    {
        if ($platfrom = $notification->getAdaptyPlatform()) {
            return $this->{'adapty' . mb_ucfirst($platfrom) . 'GroupId'};
        }

        return $this->groupId;
    }
}