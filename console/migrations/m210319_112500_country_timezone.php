<?php

use yii\db\Migration;

/**
 * Class m210319_112500_country_timezone
 */
class m210319_112500_country_timezone extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('countries', 'timezone', $this->string(32));
        $countries = \common\models\Country::find()->all();
        /** @var \common\models\Country $country */
        foreach ($countries as $country) {
            if ($country->code == 'PL') {
                $timezone = 'Europe/Warsaw';
            }
            else {
                $timezone = \common\models\Source::find()->select('timezone')->where(['IS NOT', 'timezone', null])->byCountry($country->code)->scalar();
            }
            $country->updateAttributes([
                'timezone' => $timezone
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('countries', 'timezone');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m210319_112500_country_timezone cannot be reverted.\n";

        return false;
    }
    */
}
