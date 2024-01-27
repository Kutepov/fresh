<?php namespace common\models;

use api\models\FolderSource;
use api\models\FolderSourceUrl;
use backend\traits\MultilingualModelTrait;
use common\behaviors\ActiveRecordUUIDBehavior;
use common\behaviors\CarbonBehavior;
use common\components\helpers\Api;
use common\components\multilingual\behaviors\MultilingualBehavior;
use yeesoft\multilingual\db\MultilingualLabelsTrait;
use yii\helpers\Inflector;

/**
 * This is the model class for table "folders".
 *
 * @property string $id
 * @property string $category_id
 * @property integer $default
 * @property string $created_at
 * @property string $updated_at
 * @property integer $priority
 *
 * @property-read \common\models\Source[] $sources
 * @property-read \common\models\SourceUrl[] $sourcesUrls
 * @property-read \common\models\Category $category
 */
class Folder extends \yii\db\ActiveRecord
{
    use MultilingualLabelsTrait;
    use MultilingualModelTrait;

    public const SCENARIO_GROUP_BY_SOURCES = 'group_by_sources';

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'folders';
    }

    public function behaviors()
    {
        return [
            'multilingual' => [
                'class' => MultilingualBehavior::class,
                'attributes' => [
                    'title'
                ]
            ],
            [
                'class' => CarbonBehavior::class,
                'attributes' => ['created_at', 'updated_at']
            ],
            ActiveRecordUUIDBehavior::class
        ];
    }

    public function scenarios()
    {
        $scenarios = parent::scenarios();
        $scenarios[self::SCENARIO_GROUP_BY_SOURCES] = $scenarios[self::SCENARIO_DEFAULT];
        return $scenarios;
    }

    public function fields()
    {
        $fields = [
            'id',
            'category_id',
            'title' => function () {
                return $this->title ?: Inflector::humanize(str_replace('-', '_', $this->category->name));
            },
        ];

        if (Api::version(Api::V_2_20)) {
            $fields['category_alias'] = function () {
                return $this->category->name;
            };

            $fields['icon'] = function () {
                if ($this->category->icon) {
                    return $this->category->icon . '?' . $this->updated_at;
                }

                return null;
            };
        }

        if ($this->scenario === self::SCENARIO_GROUP_BY_SOURCES) {
            $fields['sources'] = function () {
                $sources = [];
                foreach ($this->sourcesUrls as $sourcesUrl) {
                    if (!isset($sources[$sourcesUrl->source_id])) {
                        $sources[$sourcesUrl->source_id] = $sourcesUrl->source;
                        $sources[$sourcesUrl->source_id]->populateRelation('urls', []);
                    }
                    $urls = $sources[$sourcesUrl->source_id]->urls;
                    $urls[] = $sourcesUrl;
                    $sources[$sourcesUrl->source_id]->populateRelation('urls', $urls);
                }
                return array_values($sources);
            };
        } else {
            $fields[] = 'sourcesUrls';
        }

        return $fields;
    }

    public function getCategory()
    {
        return $this->hasOne(Category::class, [
            'id' => 'category_id'
        ]);
    }

    public function getCountries()
    {
        return $this->hasMany(FolderCountry::class, [
            'folder_id' => 'id'
        ])->joinWith('countryModel')->orderBy(['countries.name' => SORT_ASC]);
    }

    public static function find()
    {
        return new \common\queries\Folder(get_called_class());
    }

    public function getSourcesUrls()
    {
        return $this->hasMany(FolderSourceUrl::class, [
            'folder_id' => 'id'
        ]);
    }

    public function getSources()
    {
        return $this->hasMany(FolderSource::class, [
            'id' => 'source_id'
        ])->via('sourcesUrls');
    }

    public static function findById(string $id): ?self
    {
        return self::findOne(['id' => $id]);
    }


}
