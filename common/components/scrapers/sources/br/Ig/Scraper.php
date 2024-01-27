<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\br\Ig;

use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\Config;
use common\components\scrapers\common\services\HashImageService;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\br\Ig;
 *
 * @Config (
 * timezone="America/Sao_Paulo", urls={
 * "https://ultimosegundo.ig.com.br/brasil/",
 * "https://canaldopet.ig.com.br/",
 * "https://ultimosegundo.ig.com.br/ciencia/",
 * "https://delas.ig.com.br/",
 * "https://ultimosegundo.ig.com.br/dino/",
 * "https://economia.ig.com.br/",
 * "https://ultimosegundo.ig.com.br/educacao/",
 * "https://esporte.ig.com.br/",
 * "https://gente.ig.com.br/",
 * "https://igmais.ig.com.br/",
 * "https://ultimosegundo.ig.com.br/mundo/",
 * "https://ultimosegundo.ig.com.br/noticias/",
 * "https://ultimosegundo.ig.com.br/policia/",
 * "https://saude.ig.com.br/",
 * "https://tecnologia.ig.com.br/"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{
    /**
     * @var XPathParserV2
     */
    private $XPathParser;

    /** @var HashImageService */
    private $hashImageService;

    public function __construct(
        XPathParserV2    $XPathParser,
        HashImageService $hashImageService,
        $config = []
    )
    {
        $this->XPathParser = $XPathParser;
        $this->hashImageService = $hashImageService;

        parent::__construct($config);
    }

    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents());
            return $this->XPathParser->parseDescription($html, '//div[@itemprop="articleBody"]//p[1]');
        });
    }

    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $requestUrl = $this->getNewsByRequest($url);
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $requestUrl));
            $responseJson = json_decode($response->getBody()->getContents(), true);
            $news = $responseJson['response'];
            $news = array_reverse($news['docs']);
            $lastAddedPublicationTime = $this->lastPublicationTime;
            $lastAddedPublicationTime = $lastAddedPublicationTime->setTimezone($this->timezone);

            $result = [];
            foreach ($news as $key => $value) {
                try {
                    if (!($pageLink = $value['url'])) {
                        continue;
                    }

                    if (!preg_match('#^https?://#', $pageLink)) {
                        $pageLink = 'https://' . $pageLink;
                    }
                    $pageLink = preg_replace('#^http://#', 'https://', $pageLink);

                    $publicationDate = $this->createDateFromString($value['startDate']);

                    $imgUrl = null;
                    foreach (array_reverse($value) as $index => $imgItem) {
                        if (stripos($index, 'urlImgEmp_') !== false && $index !== 'urlImgEmp_idCorteImagem' && $index !== 'urlImgEmp_urlImagemOriginal') {
                            $imgUrl = $imgItem;
                            break;
                        }
                    }

                    $hashImage = null;
                    if ($imgUrl) {
                        $hashImage = $this->hashImageService->hashImage($imgUrl);
                    }

                    if ($publicationDate > $lastAddedPublicationTime) {
                        $title = $value['titulo'];
                        $result[] = new ArticleItem($pageLink, $title, $publicationDate, $hashImage);
                    }
                } catch (\Throwable $exception) {
                    $this->logArticleItemException($exception, $pageLink);
                }
            }

            yield $result;
        });
    }


    public function getNewsByRequest($url): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        $hostParts = explode('.', $host);

        $paths = parse_url($url, PHP_URL_PATH);
        $paths = trim($paths, '/');
        $comb = $paths ? "comb_termos={$paths}" : '';

        $size = 50;
        $site = $hostParts[0] ? "site={$hostParts[0]}" : '';
        $requestPoint = "https://{$hostParts[0]}.ig.com.br/_indice/noticias/select?start=0&size={$size}&{$site}&{$comb}&wt=json";
        return $requestPoint;
    }
}
