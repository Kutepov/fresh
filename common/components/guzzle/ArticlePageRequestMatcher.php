<?php namespace common\components\guzzle;

use common\models\SourceUrl;
use Kevinrob\GuzzleCache\Strategy\Delegate\RequestMatcherInterface;
use Psr\Http\Message\RequestInterface;

class ArticlePageRequestMatcher implements RequestMatcherInterface
{
    /** @var string[] */
    private $sourcesUrls;

    public function __construct()
    {
        $this->sourcesUrls = SourceUrl::find()
            ->enabled()
            ->select('sources_urls.url')
            ->column();
    }

    public function matches(RequestInterface $request): bool
    {
        // Запрос через прокси - для проверки необходимо вернуть оригинальную схему в урле
        if ($request->hasHeader('X-Original-Scheme')) {
            $url = (string)$request->getUri()->withScheme($request->getHeader('X-Original-Scheme')[0]);
        } else {
            $url = (string)$request->getUri();
        }
        return !in_array($url, $this->sourcesUrls, true);
    }
}