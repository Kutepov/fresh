<?php

use yii\db\Migration;

/**
 * Class m210412_122141_france
 */
class m210412_122141_france extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->insert('countries', [
            'code' => 'FR',
            'name' => 'France',
            'image' => 'country/fr.jpg',
            'timezone' => 'Europe/Paris'
        ]);

        $categories = [
            '0494e635-d04a-42eb-b4c6-328387d37aae' => 'Sciences',
            '1c3542d9-2074-46d1-8128-5ffb724d882d' => 'Entertainment',
            '46cd3082-b7b6-4f3a-a445-001fc2b12cc4' => 'Automobile',
            '4c14c556-4291-40f8-b5aa-7e7809470a6b' => 'Enquête',
            '856995dc-e0d0-4c21-9bc3-aae8a53f4f5d' => 'Différent',
            '97a8a43c-ec6b-478c-8f42-51da88c542c3' => 'Société',
            '9fcbd3e2-9918-452c-86e1-c187b736b6ea' => 'Monde',
            'a7193b4a-9ad3-4312-98a7-85efab2902b7' => 'Sport',
            'b72d1e8f-503e-4cda-94d2-f5779af32634' => 'Santé',
            'cf966295-9ea0-4879-98ee-5d987e4f2d2f' => 'High Tech',
            'e35e1c53-816c-4187-8c7d-5238218c1fbb' => 'Politique',
            'ef5c5f95-2035-41ff-a5e1-71bc1a51c4c1' => 'Économie'
        ];

        foreach ($categories as $id => $title) {
            $this->update('categories_lang', [
                'title' => $title
            ], [
                'owner_id' => $id,
                'language' => 'fr'
            ]);
        }


    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m210412_122141_france cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m210412_122141_france cannot be reverted.\n";

        return false;
    }
    */
}
