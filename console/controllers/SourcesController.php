<?php declare(strict_types=1);

namespace console\controllers;

use common\components\guzzle\Guzzle;
use common\models\SourceUrl;
use common\services\Requester;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use function GuzzleHttp\Promise\each_limit;

class SourcesController extends Controller
{
    public function actionParseTitles()
    {
        $sourcesUrls = SourceUrl::find()
            ->where([
                'OR',
                ['IS', 'name', null],
                ['=', 'name', ''],
            ])
            ->all();

        $requester = \Yii::$container->get(Requester::class);

        $generator = function () use (&$sourcesUrls, &$requester) {
            foreach ($sourcesUrls as $url) {
                yield $requester->sendAsyncRequestWithProxy(new Request('GET', $url->url))->then(function (ResponseInterface $response) use ($url) {
                    $body = $response->getBody()->getContents();
                    if (preg_match('#<title(?:[^>]+|)>(.*?)</title>#siu', $body, $m)) {
                        $title = preg_replace('#[\s]+#si', ' ', $m[1]);
                        $title = htmlspecialchars_decode($title);
                        $title = html_entity_decode($title, ENT_COMPAT | ENT_QUOTES);
                        $title = explode('|', $title);
                        $title = $title[0];
                        $title = explode(' — ', $title);
                        $title = $title[0];
                        $title = explode(' - ', $title);
                        $title = trim($title[0]);

                        if ($title && !stristr($title, 'Just a moment') && !stristr($title, 'Not found') && !stristr($title, '403 Forbidden') &&
                        !stristr($title, 'Attention Required!')) {
                            echo $title . PHP_EOL;
                            $url->updateAttributes(['name' => $title]);
                        }
                    }

                })->otherwise(function ($err) use($url) {
                    echo $url->url.': '.$err->getMessage().PHP_EOL;
                });
            }
        };

        each_limit($generator(), 20)->wait();
    }
}