<?php

use Ramsey\Uuid\Uuid;
use yii\db\Migration;

/**
 * Class m230322_101200_folders
 */
class m230322_101200_folders extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function up()
    {//
        $this->createTable('folders', [
            'id' => $this->char(36)->unique()->notNull(),
            'category_id' => $this->char(36),
            'default' => $this->boolean()->defaultValue(0),
            'created_at' => $this->timestamp()->defaultValue(new \yii\db\Expression('CURRENT_TIMESTAMP')),
            'updated_at' => $this->timestamp()->defaultValue(new \yii\db\Expression('CURRENT_TIMESTAMP')),
            'priority' => $this->integer()
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');

        $this->createIndex('idx-folder-category', 'folders', 'category_id');
        $this->addPrimaryKey('pk-folder', 'folders', 'id');

        $this->createTable('folders_lang', [
            'id' => $this->primaryKey(),
            'owner_id' => $this->char(36)->notNull(),
            'language' => $this->string(5),
            'title' => $this->string(320)
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');

        $this->createIndex('idx-unique-folder_lang', 'folders_lang', ['owner_id', 'language'], true);
        $this->createIndex('idx-folder_lang', 'folders_lang', 'language');
        $this->addForeignKey('fk-folder_lang-folder', 'folders_lang', 'owner_id', 'folders', 'id', 'CASCADE', 'CASCADE');

        $this->createTable('folders_countries', [
            'folder_id' => $this->char(36)->notNull(),
            'country' => $this->string(2),
            'articles_exists' => $this->boolean()->defaultValue(0)
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB');

        $this->addPrimaryKey('pk-folder-country', 'folders_countries', ['country', 'folder_id']);
        $this->createIndex('idx-folder_country-folder_id', 'folders_countries', 'folder_id');

        $this->addColumn('sources_urls', 'folder_id', $this->char(36)->after('category_id'));

        $categories = \common\models\Category::find()->all();
        foreach ($categories as $category) {

            $this->insert('folders', [
                'id' => $id = Uuid::uuid1()->toString(),
                'category_id' => $category->id,
                'default' => (int)($category->name === 'default'),
                'priority' => $category->name === 'default' ? -1 : $category->priority
            ]);

            $this->update('sources_urls', [
                'folder_id' => $id
            ], [
                'category_id' => $category->id
            ]);

            $langs = (new \yii\db\Query())->from('categories_lang')
                ->where(['owner_id' => $category->id])
                ->select('*')
                ->all();

            foreach ($langs as $lang) {
                $this->insert('folders_lang', [
                    'owner_id' => $id,
                    'language' => $lang['language'],
                    'title' => $lang['title']
                ]);
            }

            $countries = (new \yii\db\Query())->from('categories_countries')
                ->where(['category_id' => $category->id])
                ->select('*')
                ->all();

            foreach ($countries as $country) {
                $this->insert('folders_countries', [
                    'folder_id' => $id,
                    'country' => $country['country'],
                    'articles_exists' => $country['articles_exists']
                ]);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function down()
    {
        $this->dropTable('folders_lang');
        $this->dropTable('folders_countries');
        $this->dropTable('folders');
        $this->dropColumn('sources_urls', 'folder_id');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m230322_101200_folders cannot be reverted.\n";

        return false;
    }
    */
}
