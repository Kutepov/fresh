<?php declare(strict_types=1);

namespace common\services\apps\attribution;

use Carbon\Carbon;
use common\models\App;
use common\queue\jobs\AppFirstOpenEventJob;

class AttributionService
{
    public function collect(App     $app,
                            string  $organizationId,
                            string  $campaignId,
                            string  $adGroupId,
                            string  $keywordId,
                            string  $adId,
                            string  $country,
                            ?string $clickDate,
                            string  $service = 'Apple Search Ads')
    {
        if (!$app->attribution_service) {
            $app->updateAttributes([
                'attribution_service' => $service,
                'attr_organization_id' => $organizationId,
                'attr_campaign_id' => $campaignId,
                'attr_ad_group_id' => $adGroupId,
                'attr_keyword_id' => $keywordId,
                'attr_ad_id' => $adId,
                'attr_country' => preg_replace('#Optional\("(.*?)"\)#is', '$1', $country),
                'attr_click_date' => $clickDate ? Carbon::parse($clickDate)->timestamp : null
            ]);

            \Yii::$app->queue->push(new AppFirstOpenEventJob([
                'appId' => $app->id
            ]));
        }
    }

    public function setIdfa(App $app, string $idfa)
    {
        if (!$app->idfa && $idfa) {
            $app->updateAttributes(['idfa' => $idfa]);
        }
    }
}