<?php namespace backend\models;

use common\components\caching\Cache;
use common\components\queue\jobs\ArticlesChangeCategoryJob;
use common\components\queue\jobs\CalcArticlesInCategoriesJob;
use yii\helpers\ArrayHelper;

class SourceUrl extends \common\models\SourceUrl
{
    public function setDefaults()
    {
        $this->enabled = true;
        $this->ios_enabled = true;
        $this->android_enabled = true;
    }

    public function rules()
    {
        return ArrayHelper::merge(parent::rules(), [
            ['url_skip_regexp', 'string'],
            ['url_skip_regexp', 'trim'],
            ['url_skip_regexp', 'validateRegexp']
        ]);
    }

    function validateRegexp()
    {
        if ($this->url_skip_regexp) {
            try {
                preg_match('#' . $this->url_skip_regexp . '#siu', "");
            } catch (\Throwable $e) {
                $this->addError('url_skip_regexp', 'Неверное рег. выражение');
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'url' => 'Url',
            'category_id' => 'Категория',
            'source_id' => 'Источник',
            'category_name' => 'Category Name',
            'last_scraped_at' => 'Последняя проверка',
            'last_scraped_article_date' => 'Последняя новость',
            'created_at' => 'Создано',
            'ios_enabled' => 'iOS',
            'android_enabled' => 'Android',
            'enabled' => 'Включен',
            'timezone' => 'Часовой пояс',
            'class' => 'Парсер',
            'last_scraped_article_date_disabled' => 'Игнорировать дату последней спарсеной новости',
            'note' => 'Заметка',
            'url_skip_regexp' => 'Рег. выражение для пропуска новостей по url',
            'subscribers_count' => 'Подписки',
            'countries_ids' => 'Страны'
        ];
    }

    public static function getListValuesBySourceId(string $source_id, string $field, string $indexBy = 'id'): array
    {
        return ArrayHelper::map(self::find()
            ->select([$field, 'id'])
            ->indexBy($indexBy)
            ->distinct($field)
            ->where(['source_id' => $source_id])
            ->asArray()
            ->all(),
            $indexBy,
            $field
        );
    }

    public function getArticles()
    {
        return $this->hasMany(Article::class, [
            'source_url_id' => 'id'
        ]);
    }

    public function afterSave($insert, $changedAttributes)
    {
        if (isset($changedAttributes['enabled']) && $this->enabled && !$this->source->enabled) {
            $this->source->updateAttributes([
                'enabled' => $this->enabled
            ]);
        }

        \Yii::$app->countersQueue->push(new CalcArticlesInCategoriesJob());

        if ($changedAttributes['category_id']) {
            \Yii::$app->countersQueue->push(new ArticlesChangeCategoryJob([
                'sourceUrlId' => $this->id,
                'oldCategoryId' => $changedAttributes['category_id']
            ]));
        }


        Cache::clearByTag(Cache::TAG_SOURCES_LIST);
        parent::afterSave($insert, $changedAttributes);
    }
}
