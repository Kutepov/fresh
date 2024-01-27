<?php namespace api\controllers;

use common\components\helpers\Api;
use common\models\Source;
use yii\db\Expression;
use yii\filters\HttpCache;
use yii\web\Response;
use yii;

class WebviewController extends Controller
{
    public function behaviors()
    {
        return yii\helpers\ArrayHelper::merge(parent::behaviors(), [
            [
                'class' => HttpCache::class,
                'only' => ['index'],
                'cacheControlHeader' => 'public, max-age=3600',
                'etagSeed' => function ($action, $params) {
                    return implode('|', [
                            Source::find()->select(new Expression('MAX(updated_at)'))->scalar(),
                            $this->country,
                            $this->articlesLanguage,
                            $this->language
                        ]
                    );
                }
            ],
        ]);
    }

    private const RULES = [
        [
            'trigger' => [
                'url-filter' => '.*',
                'if-domain' => [
                    '*optad360.io'
                ]
            ],
            'action' => [
                'type' => 'block'
            ]
        ],
        [
            'trigger' => [
                'url-filter' => '.*'
            ],
            'action' => [
                'type' => 'css-display-none',
                'selector' => '.adsbygoogle[style*=\'position: fixed\'],.OUTBRAIN.ob-fixed,.OUTBRAIN.ob-shrink.ob-bottom-box,marfeel-flowcards'
            ]
        ]
    ];

    public function actionIndex()
    {

        /**
         * ##.OUTBRAIN.ob-fixed
         * ##.OUTBRAIN.ob-shrink.ob-bottom-box
         * ##marfeel-flowcards
         * ##.adsbygoogle[style*='position: fixed']
         * https://*optad360.io
         */

        $rules = self::RULES;

        $sources = Source::find()
            ->withAdBlockRules()
            ->all();

        foreach ($sources as $source) {
            $rules[] = $source->getWebkitContentRules();
        }

        if (Api::version(Api::V_2_20)) {
            $adblockRules = yii\helpers\Json::decode(file_get_contents(Yii::getAlias('@api/adblock-rules.json')));
            $rules = yii\helpers\ArrayHelper::merge($rules, $adblockRules);
        }

        return $this->convertRulesIfNeeded($rules);
    }

    private function convertRulesIfNeeded(array $rules)
    {
        if ($this->currentApp->isIos) {
            return $rules;
        }

        $newRules = [];

        foreach ($rules as $rule) {
            switch ($rule['action']['type']) {
                case 'block':
                    if (isset($rule['trigger']['if-domain'])) {
                        $domains = $rule['trigger']['if-domain'];
                    }
                    else {
                        $newRules[] = $rule['trigger']['url-filter'];
                        break;
                    }

                    foreach ($domains as $domain) {
                        $newRules[] = '||' . $this->adblockDomain($domain) . '^';
                    }
                    break;

                case 'css-display-none':
                    if (isset($rule['trigger']['if-domain'])) {
                        $domains = $rule['trigger']['if-domain'];
                    }
                    else {
                        $domains = [''];
                    }

                    $selectors = array_filter(explode(',', $rule['action']['selector']));
                    foreach ($domains as $domain) {
                        foreach ($selectors as $selector) {
                            $newRules[] = $this->adblockDomain($domain) . '##' . $selector;
                        }
                    }
                    break;
            }
        }

        Yii::$app->response->format = Response::FORMAT_RAW;
        Yii::$app->response->headers->set('Content-type', 'text/plain; charset=UTF-8');

        return implode("\n", $newRules);
    }

    private function adblockDomain($domain)
    {
        return preg_replace('#^\*#', '', $domain);
    }
}