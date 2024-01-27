<?php declare(strict_types=1);

namespace common\services\apps\entities;

use common\models\App;

class SplitmetricsAcquireEvent
{
    public string $userId;
    public string $event;
    public int $installDate;
    public int $eventDate;
    public string $type;

    public string $adId;
    public string $adAccountId;
    public string $appId = '1397865865';
    public ?int $clickDate;
    public string $country;
    public ?string $idfa;

    public string $adGroupId;
    public string $campaignId;
    public string $keywordId;

    public ?float $revenue;
    public ?string $currency;

    public const TYPE_IN_APP = 'in-app';
    public const TYPE_INSTALL = 'install';

    /**
     * @param int $installDate
     * @param int $eventDate
     * @param string $keywordId
     * @param string $adGroupId
     * @param string $campaignId
     * @param ?float $revenue
     * @param ?string $currency
     */
    public function __construct(string  $event,
                                int     $installDate,
                                int     $eventDate,
                                string  $adAccountId,
                                string  $adId,
                                string  $country,
                                string  $keywordId,
                                string  $adGroupId,
                                string  $campaignId,
                                string  $userId,
                                ?string $idfa = null,
                                ?int    $clickDate = null,
                                ?float  $revenue = null,
                                ?string $currency = null,
                                string  $type = self::TYPE_IN_APP
    )
    {
        $this->event = $event;
        $this->installDate = $installDate;
        $this->eventDate = $eventDate;
        $this->adAccountId = $adAccountId;
        $this->keywordId = $keywordId;
        $this->adGroupId = $adGroupId;
        $this->campaignId = $campaignId;
        $this->clickDate = $clickDate;
        $this->adId = $adId;
        $this->country = $country;
        $this->revenue = $revenue;
        $this->currency = $currency;
        $this->idfa = $idfa;
        $this->userId = $userId;
        $this->type = $type;
    }

    public static function createForApp(App $app, ?EventContract $sourceEvent = null, &$debug = null): ?self
    {
        if (
            $app->attribution_service === 'Apple Search Ads' &&
            ($campaignId = $app->attr_campaign_id) &&
            ($adGroupId = $app->attr_ad_group_id) &&
            ($keywordId = $app->attr_keyword_id) &&
            ($adAccountId = $app->attr_organization_id) &&
            ($country = $app->attr_country)
        ) {
            $revenue = null;
            $currency = null;
            if ($sourceEvent !== null) {
                $eventName = $sourceEvent->getEvent();
                $eventDate = $sourceEvent->getEventDate();
                $revenue = $sourceEvent->getAmount();
                $currency = $sourceEvent->getCurrency();
            } else {
                $eventName = 'install';
                $eventDate = $app->created_at;

                if ($app->created_at < time() - 1200) {
                    return null;
                }
            }

            $splitmetricsEvent = new self(
                $eventName,
                $app->created_at,
                $eventDate,
                $adAccountId,
                $app->attr_ad_id,
                $country,
                $keywordId,
                $adGroupId,
                $campaignId,
                $app->uid,
                $app->idfa,
                $app->attr_click_date,
                $revenue,
                $currency
            );

            if ($sourceEvent === null) {
                $splitmetricsEvent->type = self::TYPE_INSTALL;
            }

            return $splitmetricsEvent;
        }

        $logKey = 'apphud';
        if ($sourceEvent instanceof QonversionEvent) {
            $logKey = 'qonversion';
        }

        $debug['error'] = 'No app attribution.';
        \Yii::info($debug, $logKey . '-events');

        return null;
    }

    public function getWebhookQueryParams(): array
    {
        return array_filter([
            'source' => 'Apple Search Ads',
            'adaccount_id' => $this->adAccountId,
            'ad_id' => $this->adId,
            'idfa' => $this->idfa,
            'app_id' => $this->appId,
            'user_id' => $this->userId,
            'campaign_id' => $this->campaignId,
            'adgroup_id' => $this->adGroupId,
            'keyword_id' => $this->keywordId,
            'revenue' => $this->revenue,
            'currency' => $this->currency,
            'tap_time' => $this->clickDate,
            'open_time' => $this->installDate,
            'event_time' => $this->eventDate,
            'name' => $this->event,
            'type' => $this->type,
            'country_or_region' => $this->country
        ]);
    }
}