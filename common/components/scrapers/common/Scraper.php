<?php namespace common\components\scrapers\common;

use Carbon\Carbon;
use common\components\guzzle\Guzzle;
use common\components\scrapers\common\symfony\ParsingModule\Infrastructure\Exception\MissingParserException;
use common\contracts\Logger;
use common\models\SourceUrl;
use common\services\Requester;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Psr\Http\Message\ResponseInterface;
use yii\base\Component;
use yii\base\Exception;
use yii\helpers\ArrayHelper;
use Yii;

/**
 * Class Scraper
 * @package common\components\scrapers\common
 *
 * @property string|null $calledMethod
 * @property Guzzle $guzzle
 * @property \DateTimeZone $timezone
 * @property Carbon $lastPublicationTime
 *
 * @see SourceUrl::getScraper()
 */
abstract class Scraper extends Component implements ArticlesListScraper
{
    public $id;
    public $sourceId;
    public $url;
    public $timezone;
    public $lastPublicationTime;
    public $urlSkipRegexp;

    public $debug = false;

    /** @var Logger */
    protected $logger;
    protected Requester $requester;

    public function __construct($config = [])
    {
        $this->requester = Yii::$container->get(Requester::class);
        $this->logger = Yii::$container->get(Logger::class);

        parent::__construct($config);
        $this->requester->setDebug($this->debug);
    }

    /**
     * @param Request $request
     * @param array $options
     * @return PromiseInterface
     */
    protected function sendAsyncRequest(Request $request, $options = []): PromiseInterface
    {
        return $this->requester->sendAsyncRequest($request, ArrayHelper::merge([
            'source_id' => $this->id,
        ], $options));
    }

    /**
     * @param Request $request
     * @param array $options
     * @return PromiseInterface
     */
    protected function sendAsyncRequestWithProxy(Request $request, $options = []): PromiseInterface
    {
        $attemptForProxyEnabling = $this->proxyEnablingAttempt();
        $options[Guzzle::PROXY_ENABLING_ATTEMPT] = $attemptForProxyEnabling;
        return $this->requester->sendAsyncRequestWithProxy($request, $options);
    }

    abstract public function parseArticlesList(string $url): PromiseInterface;

    protected function createDateFromString($date, $trimZTimeZone = false): Carbon
    {
        if ($trimZTimeZone) {
            $date = trim($date, 'Z ');
        }
        $date = Carbon::parse($date, $this->timezone);
        $date->setTimezone($this->timezone);

        if ($date->year >= 2038) {
            throw new Exception('Wrong date/time value: ' . $date);
        }

        return $date;
    }

    protected function isNeedSkipArticle(ResponseInterface $response): bool
    {
        return ($response->getHeader(CacheMiddleware::HEADER_CACHE_INFO)[0] ?? null) === CacheMiddleware::HEADER_CACHE_HIT;
    }

    /**
     * @throws \Throwable
     */
    protected function logArticleItemException(\Throwable $exception, ?string $articleUrl = null): void
    {
        if (!($exception instanceof MissingParserException)) {
            if (defined('SCRAPER_DEBUG')) {
                throw $exception;
            }

            $this->logger->scraperArticleItemException($exception, $this, $articleUrl);
        }
    }

    /**
     * С какой попытки запроса начать использовать прокси.
     * null - не использовать прокси
     * 0 - с самой первой попытки
     * 1-.. - номер повторной попытки
     * @return int|null
     */
    protected function proxyEnablingAttempt(): ?int
    {
        return Guzzle::PROXY_ALWAYS_ENABLED;
    }
}