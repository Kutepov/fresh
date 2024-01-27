<?php namespace common\services\poster;

use common\components\guzzle\Guzzle;
use common\contracts\Logger;
use common\contracts\Poster;
use common\models\Article;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\Each;
use GuzzleHttp\RequestOptions;
use yii\base\BaseObject;
use yii\helpers\Json;

class Telegram extends BaseObject implements Poster
{
    private $guzzle;
    private $logger;
    private $apiUrl = 'https://api.telegram.org/';

    public $botApiToken;

    public function __construct(Client $guzzle, Logger $logger, $config = [])
    {
        $this->guzzle = $guzzle;
        $this->logger = $logger;

        parent::__construct($config);
    }

    public function postArticle(Article $article): void
    {
        try {
            $preparedText = '<strong>' . $article->title . '</strong>' . PHP_EOL;

            if ($article->description) {
                $description = str_replace(' .', '.', $article->description);
                $text = explode(' ', $description);
                $firstText = array_slice($text, 0, count($text) - 2);
                $lastText = array_slice($text, count($text) - 2, 2);
                if (in_array(reset($lastText), ['-', '—'])) {
                    $firstText = array_slice($text, 0, count($text) - 1);
                    $lastText = array_slice($text, count($text) - 1, 1);
                }

                $preparedText .= implode(' ', $firstText);
                $lastText = implode(' ', $lastText);
                if (preg_match('#[\.…]+$#u', $lastText, $suffix)) {
                    $suffix = $suffix[0];
                }
                else {
                    $suffix = '';
                }

                $lastText = trim($lastText, ' .…,');

                $preparedText .= ' <a href="' . $article->url . '">' . $lastText . '</a>' . $suffix;

                $preparedText .= PHP_EOL . PHP_EOL . '<a href="https://t.me/+EWPvLt7ziXgyNDJi">Новини України</a>';

                if ($channelId = $article->source->telegram_channel_id) {
                    if ($article->mediaForTelegram) {
                        $this->postArticleWithMedia($article, $channelId, $preparedText);
                    }
                    else {
                        $this->postArticleWithoutMedia($channelId, $preparedText);
                    }
                }
            }

        } catch (\Throwable $e) {
            $this->logger->warning($e);
        }
    }

    private function postArticleWithoutMedia($channelId, $text)
    {
        $this->guzzle->get($this->apiUrl . 'bot' . $this->botApiToken . '/sendMessage', [
            RequestOptions::QUERY => [
                'chat_id' => $channelId,
                'text' => $text,
                'parse_mode' => 'html',
                'disable_web_page_preview' => true
            ]
        ]);
    }

    private function postArticleWithMedia(Article $article, $channelId, $text)
    {
        $media = $article->mediaForTelegram;
        $media[0]['caption'] = $text;
        $media[0]['parse_mode'] = 'html';

        $this->guzzle->post($this->apiUrl . 'bot' . $this->botApiToken . '/sendMediaGroup', [
            RequestOptions::JSON => [
                'chat_id' => $channelId,
                'media' => $media
            ]
        ]);
    }

    public function approveRequests(): void
    {
        $lastApprovedId = \Yii::$app->settings->get('SettingsTelegramForm', 'lastUpdateId', false);
        $approvePeriod = \Yii::$app->settings->get('SettingsTelegramForm', 'approvePeriod', 12);

        $updates = $this->guzzle->post($this->apiUrl . 'bot' . $this->botApiToken . '/getUpdates', [
            RequestOptions::JSON => array_filter([
                'offset' => $lastApprovedId ? $lastApprovedId + 1 : false
            ])
        ])->getBody()->getContents();

        $updates = Json::decode($updates)['result'] ?? [];

        $approvesRequests = [];
        $lastUpdateId = null;
        foreach ($updates as $update) {
            if (isset($update['chat_join_request']) && !$update['chat_join_request']['from']['is_bot'] && $update['chat_join_request']['date'] <= time() - 3600 * $approvePeriod) {
                $approvesRequests[] = $this->guzzle->getAsync($this->apiUrl . 'bot' . $this->botApiToken . '/approveChatJoinRequest', [
                    RequestOptions::QUERY => [
                        'chat_id' => $update['chat_join_request']['chat']['id'],
                        'user_id' => $update['chat_join_request']['from']['id']
                    ]
                ]);
                $lastUpdateId = $update['update_id'];
            }
        }

        Each::ofLimit($approvesRequests, 4)->wait();

        if ($lastUpdateId > $lastApprovedId) {
            \Yii::$app->settings->set('SettingsTelegramForm', 'lastUpdateId', $lastUpdateId);
        }
    }
}