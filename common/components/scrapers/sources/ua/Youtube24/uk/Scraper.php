<?php

declare(strict_types=1);

namespace common\components\scrapers\sources\ua\Youtube24\uk;

use common\components\scrapers\common\YoutubeRssScraper;
use common\components\scrapers\common\Config;

/**
 * Class Scraper
 * @package common\components\scrapers\sources\ua\Epravda\uk
 *
 * @Config (timezone="Europe/Kiev", urls={
 * "https://www.youtube.com/feeds/videos.xml?channel_id=UCEC4D0dTTJr_EEnEJz15hnQ"
 * })
 */
class Scraper extends YoutubeRssScraper
{
}