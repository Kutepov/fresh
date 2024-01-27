<?php

use yii\db\Migration;

/**
 * Class m220117_105137_move_adblock_rules_into_db
 */
class m220117_105137_move_adblock_rules_into_db extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $rules = [
            [
                'trigger' => [
                    'url-filter' => '.*',
                    'if-domain' => [
                        '*interia.pl'
                    ]
                ],
                'action' => [
                    'type' => 'css-display-none',
                    'selector' => '#adxFixedAdMainCont'
                ]
            ],
            [
                'trigger' => [
                    'url-filter' => '.*',
                    'if-domain' => [
                        '*se.pl'
                    ]
                ],
                'action' => [
                    'type' => 'css-display-none',
                    'selector' => '#floorLayer'
                ]
            ],
            [
                'trigger' => [
                    'url-filter' => '.*',
                    'if-domain' => [
                        '*wprost.pl'
                    ]
                ],
                'action' => [
                    'type' => 'css-display-none',
                    'selector' => '.ad-aside-sticky,.ad-aside-sticky-off'
                ]
            ],
            [
                'trigger' => [
                    'url-filter' => '.*',
                    'if-domain' => [
                        '*20minutos.es'
                    ]
                ],
                'action' => [
                    'type' => 'css-display-none',
                    'selector' => '.__hnads_sticky'
                ]
            ],
            [
                'trigger' => [
                    'url-filter' => '.*',
                    'if-domain' => [
                        '*lavanguardia.com'
                    ]
                ],
                'action' => [
                    'type' => 'css-display-none',
                    'selector' => '.bottom-ad-module'
                ]
            ]
        ];

        foreach ($rules as $source) {;
            $domain = substr($source['trigger']['if-domain'][0], 1);
            $sourceEntity = \common\models\Source::find()->where(['LIKE', 'url', $domain])->one();
            $selectors = explode(',', $source['action']['selector']);

            $sourceEntity->updateAttributes([
                'adblock_css_selectors' => implode("\n", $selectors)
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m220117_105137_move_adblock_rules_into_db cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m220117_105137_move_adblock_rules_into_db cannot be reverted.\n";

        return false;
    }
    */
}
