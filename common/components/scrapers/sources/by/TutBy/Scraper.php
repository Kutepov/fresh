<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\by\TutBy;

use common\components\scrapers\common\ArticleBodyScraper;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\ExternalServiceHelper\YoutubeHelper;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\NewsCutter;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\XPathHelper\XPathParserV2;
use common\components\scrapers\common\symfony\SiteParserModule\Infrastructure\SelectorsRemover;
use common\components\scrapers\common\services\HashImageService;
use common\components\scrapers\common\Config;
use common\components\scrapers\dto\ArticleBodyNode;
use common\components\scrapers\dto\ArticleItem;
use GuzzleHttp\Promise\Coroutine;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\by\TutBy
 *
 * @Config (
 * timezone="Europe/Minsk", urls={
 * "https://sport.tut.by/rubric/biathlon/",
 * "https://sport.tut.by/rubric/hockey/",
 * "https://sport.tut.by/rubric/football/",
 * "https://sport.tut.by/rubric/tennis/",
 * "https://sport.tut.by/rubric/basketball/",
 * "https://sport.tut.by/rubric/handball/",
 * "https://sport.tut.by/rubric/aboutsport/",
 * "https://42.tut.by/",
 * "https://42.tut.by/rubric/inbelarus/",
 * "https://42.tut.by/rubric/education/",
 * "https://42.tut.by/rubric/internet/",
 * "https://42.tut.by/rubric/devices_tehno/",
 * "https://42.tut.by/rubric/weapon/",
 * "https://auto.tut.by/rubric/road/",
 * "https://auto.tut.by/rubric/autobusiness/",
 * "https://auto.tut.by/rubric/accidents/",
 * "https://auto.tut.by/rubric/offtop/",
 * "https://health.tut.by/?sort=time",
 * "https://news.tut.by/geonews/minsk/",
 * "https://news.tut.by/economics/",
 * "https://news.tut.by/society/",
 * "https://news.tut.by/world/",
 * "https://news.tut.by/culture/",
 * "https://news.tut.by/accidents/",
 * "https://news.tut.by/auto/",
 * "https://news.tut.by/finance/",
 * "https://news.tut.by/realty/",
 * "https://news.tut.by/sport/",
 * "https://news.tut.by/health/",
 * "https://news.tut.by/lady/",
 * "https://news.tut.by/it/",
 * "https://news.tut.by/afisha/",
 * "https://news.tut.by/popcorn/",
 * "https://news.tut.by/probusiness/",
 * "https://news.tut.by/press/"
 * })
 */
class Scraper extends \common\components\scrapers\common\Scraper implements ArticleBodyScraper
{
    private const OVERRIDE_REPLACE_TAGS = [];

    /**
     * @var SelectorsRemover
     */
    private $selectorsRemover;

    /**
     * @var XPathParserV2
     */
    private $XPathParser;

    /**
     * @var YoutubeHelper
     */
    private $youtubeHelper;

    /**
     * @var NewsCutter
     */
    private $newsCutter;

    public function __construct(
        SelectorsRemover $selectorsRemover,
        XPathParserV2 $XPathParserV2,
        NewsCutter $newsCutter,
        YoutubeHelper $youtubeHelper,
        $config = []
    )
    {
        $this->selectorsRemover = $selectorsRemover;
        $this->XPathParser = $XPathParserV2;
        $this->newsCutter = $newsCutter;
        $this->youtubeHelper = $youtubeHelper;

        parent::__construct($config);
    }


    public function parseArticleBody(string $url): PromiseInterface
    {
        return $this->sendAsyncRequestWithProxy(new Request('GET', $url))->then(function (ResponseInterface $response) {
            $raplaceTags = self::OVERRIDE_REPLACE_TAGS;
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');


            $selector = "//div[contains(@id, 'article_body')]|//div[@class='post-showing-type1']";

            $textNode = $html->filterXPath($selector);

            $this->selectorsRemover->remove(
                "
            //blockquote[contains(@class, 'instagram-media')]//text()|
            //blockquote[contains(@class, 'twitter-tweet')]//text()|
            //div[contains(@class, 'fb-post')]//text()|
            //div[contains(@class, 'b-addition')]|
            //p[@class='stk-reset align-center wp-exclude-emoji']|
            ",
                $textNode
            );

            $newsLinks = $textNode->filterXPath('//a');
            $this->newsCutter->cutterByLinks($newsLinks);

            $text = $textNode->filterXPath(
                "
            //p[not(ancestor::blockquote)]|
            //blockquote|
            //blockquote[contains(@class, 'instagram-media')]|
            //blockquote[contains(@class, 'twitter-tweet')]/a|
            //iframe|
            //img|
            //h2|
            //h3|
            //ul|
            //ol|
            "
            );

            $result = $this->XPathParser->parse($text, $raplaceTags);

            $video = $this->parseVideo($textNode->filterXPath("//script[contains(text(), 'https://youtu') or contains(text(), 'https://img.tyt.by')]"));
            if ($video) {
                foreach ($video as $key => $value) {
                    $result->add(new ArticleBodyNode($value['elementName'], $value['value']));
                }
            }


            return $result;
        });
    }


    private function parseVideo(Crawler $videoScript): ?array
    {
        $video = [];

        try {
            $videoScript->each(function (Crawler $node) use ($videoScript, &$video) {
                $videoScriptText = $node->text();

                $matches = [];
                preg_match('/file\:([a-zA-z\\"\:\/0-9\.\-\_\s]*)/', $videoScriptText, $matches);
                if ($matches) {
                    $videoLink = str_replace(["'", '"'], '', $matches[1]);
                    $isYoutube = strpos($videoLink, 'https://youtu');
                    $isMp4 = (bool)(strpos($videoLink, '.mp4'));
                    if (false !== $isYoutube) {
                        $videoId = parse_url($videoLink, PHP_URL_PATH);
                        $videoId = substr($videoId, 1);
                        $video[] = ['elementName' => 'video', 'value' => $this->youtubeHelper->generateUrlForId($videoId)];
                    }
                    elseif ($isMp4) {
                        $video[] = ['elementName' => 'video-source', 'value' => $videoLink];
                    }
                }
            });

            return $video;
        } catch (\Throwable $exception) {
            return null;
        }
    }


    public function parseArticlesList(string $url): PromiseInterface
    {
        return Coroutine::of(function () use ($url) {
            $response = yield $this->sendAsyncRequestWithProxy(new Request('GET', $url));
            $html = new Crawler();
            $html->addHtmlContent($response->getBody()->getContents(), 'UTF-8');

            $selector = "//div[@id='content-band']|//div[@class='b-news']|//div[@class='b-equal-twocols']";
            $articlesNode = $html->filterXPath($selector);

            $articles = $articlesNode->filterXPath("//div[@class='news-entry big annoticed time ni']|//div[@class='col-c']//div[@class='col-i']//div[@class='col-w']|//div[@class='m-s_comment big_tn']");

            $lastAddedPublicationTime = $this->lastPublicationTime;

            $result = [];
            for ($i = $articles->count() - 1; $i >= 0; --$i) {
                $node = $articles->eq($i);
                try {
                    if ($node->attr('class') == 'm-s_comment big_tn') {

                        if ($node->previousAll()->text() == 'Реклама') {
                            continue;
                        }

                        $linkNode = $node->filterXPath("//div[@class='txt']//a")->first();
                        $pageLink = $linkNode->attr('href');
                        $pageLink = preg_replace('#^http://#', 'https://', $pageLink);

                        $title = $linkNode->text();


                        $pageContent = yield $this->sendAsyncRequestWithProxy(new Request('GET', $pageLink));
                        if ($this->isNeedSkipArticle($pageContent)) {
                            continue;
                        }
                        $html = new Crawler();
                        $html->addHtmlContent($pageContent->getBody()->getContents(), 'UTF-8');

                        $articlePubDate = $html->filterXPath("//meta[@property='article:published_time']")->first();
                        $pubDateAttr = $articlePubDate->attr('content');
                        if (!$pubDateAttr) {
                            continue;
                        }

                        $publicationDate = $this->createDateFromString($pubDateAttr);

                    }
                    else {
                        $linkNode = $node->filterXPath("//a[@class='entry__link'][2]")->first();
                        $pageLink = $linkNode->attr('href');
                        $pageLink = preg_replace('#^http://#', 'https://', $pageLink);
                        $title = $linkNode->filterXPath("//span[@class='entry-head _title']")->first()->text();

                        $articlePubDate = $node->filterXPath("//span[contains(@class, 'entry-time')]//span")->first()->attr('data-ctime');

                        $publicationDate = new \DateTime();
                        $publicationDate->setTimestamp((int)$articlePubDate);
                        $publicationDate->setTimezone($this->timezone);

                    }


                    if (preg_match('#((kupi|plati)\.tut|rebenok)\.by#i', $pageLink)) {
                        continue;
                    }


                    if ($publicationDate > $lastAddedPublicationTime) {
                        $immutableDate = \DateTimeImmutable::createFromMutable($publicationDate);

                        $result[] = new ArticleItem($pageLink, $title, $immutableDate);
                    }
                } catch (\Throwable $exception) {
                    $this->logArticleItemException($exception, $pageLink);
                }
            }

            yield $result;
        });
    }
}
