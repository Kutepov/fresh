<?php namespace backend\models;

use common\components\caching\Cache;
use common\models\CategoryCountry;
use common\models\FolderCountry;
use common\services\FoldersService;
use common\services\HashtagsService;
use common\services\MultilingualService;
use himiklab\sortablegrid\SortableGridBehavior;
use yii\db\Expression;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
use yii\web\UploadedFile;
use Yii;

class Category extends \common\models\Category
{
    public const SCENARIO_UPDATE = 'update';

    /**
     * @var MultilingualService
     */
    public $multilingualService;
    private FoldersService $foldersService;
    private HashtagsService $hashtagsService;

    public function __construct($config = [])
    {
        $this->foldersService = Yii::$container->get(FoldersService::class);
        $this->hashtagsService = Yii::$container->get(HashtagsService::class);
        $this->multilingualService = \Yii::$container->get(MultilingualService::class);
        parent::__construct($config);
    }

    public function behaviors()
    {
        $jpCondition = Yii::$app->request->get('jpCondition');
        return ArrayHelper::merge(parent::behaviors(), [
            'sort' => [
                'class' => SortableGridBehavior::class,
                'sortableAttribute' => $jpCondition ? 'jp_priority' : 'priority',
                'afterGridSort' => function (Category $model) use ($jpCondition) {
                    if ($model->folder) {
                        if ($jpCondition) {
                            $model->folder->updateAttributes([
                                'jp_priority' => $model->jp_priority
                            ]);
                        } else {
                            $model->folder->updateAttributes([
                                'priority' => $model->priority
                            ]);
                        }
                    }
                    Cache::clearByTag(Cache::TAG_CATEGORIES_LIST);
                }
            ]
        ]);
    }

    /**
     * @var UploadedFile
     */
    public $imageFile;
    /**
     * @var UploadedFile
     */
    public $iconFile;

    /**
     * @return array
     */
    public function rules()
    {
        $rules = parent::rules();
        $rules[] = [['imageFile'], 'file', 'skipOnEmpty' => true, 'extensions' => 'png, jpg', 'except' => self::SCENARIO_UPDATE];
        $rules[] = [['iconFile'], 'file', 'skipOnEmpty' => true, 'extensions' => 'svg, png, jpg', 'except' => self::SCENARIO_UPDATE];
        return $rules;
    }

    public function attributeLabels()
    {
        $labels = parent::attributeLabels();
        $labels['imageFile'] = 'Изображение';
        $labels['iconFile'] = 'Иконка в приложении';
        return $labels;
    }


    public function save($runValidation = true, $attributeNames = null)
    {
        if ($this->validate()) {
            $this->imageFile = UploadedFile::getInstance($this, 'imageFile');
            if ($this->imageFile) {
                if ($this->imageFile->saveAs(
                    Yii::getAlias('@api') . '/web/img/category/' .
                    $this->name . '.' .
                    $this->imageFile->extension
                )) {
                    $this->image = 'category/' . $this->name . '.' . $this->imageFile->extension;
                }
            }

            $this->iconFile = UploadedFile::getInstance($this, 'iconFile');
            if ($this->iconFile) {
                if ($this->iconFile->saveAs(Yii::getAlias('@api/web/img/category/icon-' . $this->name . '.' . $this->iconFile->extension))) {
                    $this->icon = 'category/icon-' . $this->name . '.' . $this->iconFile->extension;
                }
            }

            if (parent::save($runValidation, $attributeNames)) {

                $this->saveCountries($this->countriesList);
                return true;
            }
        }
        return false;
    }

    private function saveCountries($countries)
    {
        /** TODO: refactor this shit */
        if (!is_array($countries)) {
            $countries = Country::find()->select('code')->column();
        }

        foreach ($countries as $country) {
            if (!$this->getCountries()->andWhere(['categories_countries.country' => $country])->exists()) {
                $categoryCountry = new CategoryCountry();
                $categoryCountry->category_id = $this->id;
                $categoryCountry->country = $country;
                $categoryCountry->save();

                if ($this->folder) {
                    $folderCountry = new FolderCountry();
                    $folderCountry->folder_id = $this->folder->id;
                    $folderCountry->country = $country;
                    $folderCountry->save();
                }
            }
        }

        CategoryCountry::deleteAll([
            'AND',
            ['NOT IN', 'country', $countries],
            ['=', 'category_id', $this->id]
        ]);

        if ($this->folder) {
            FolderCountry::deleteAll([
                'AND',
                ['NOT IN', 'country', $countries],
                ['=', 'folder_id', $this->folder->id]
            ]);
        }
    }

    private function deleteImage()
    {
        $files = [$this->image, $this->icon];

        foreach ($files as $file) {
            $pathToFile = Yii::getAlias('@api') . '/web/img/' . $file;
            if (is_file($pathToFile)) {
                FileHelper::unlink($pathToFile);
            }
        }
    }

    public function delete()
    {
        $this->deleteImage();
        return parent::delete();
    }


    /**
     * @return string
     */
    public function getAbsoluteUrlToImage()
    {
        return Yii::getAlias('@frontendBaseUrl') . '/img/' . $this->image . '?' . $this->updated_at;
    }

    /**
     * @return string
     */
    public function getAbsoluteUrlToIcon()
    {
        return Yii::getAlias('@frontendBaseUrl') . '/img/' . $this->icon . '?' . $this->updated_at;
    }

    public function getProgressTranslates()
    {
        $totalLanguagesCount = (int)Language::find()->count();
        $emptyTranslateCount = count($this->getEmptyLanguages());
        return ['total' => $totalLanguagesCount, 'filled' => $totalLanguagesCount - $emptyTranslateCount];
    }

    public function getProgressCountries()
    {
        $totalCountriesCount = (int)Country::find()->count();
        $choiceCountruesCount = count($this->countries);
        return ['total' => $totalCountriesCount, 'choice' => $choiceCountruesCount];
    }

    public static function getForDropdown($countryCode = null)
    {
        $categories = self::find()->multilingual();
        if ($countryCode) {
            $categories->forCountry($countryCode);
        }
        $categories->orderBy('name');
        $categories = $categories->all();
        $result = [];
        foreach ($categories as $category) {
            $result[$category->id] = $category->title;
        }
        return $result;
    }

    public function afterSave($insert, $changedAttributes)
    {
        Cache::clearByTag(Cache::TAG_CATEGORIES_LIST);

        parent::afterSave($insert, $changedAttributes);

        /** Создаем/Обновляем папку для категории */
        $this->foldersService->createOrUpdateFolderForCategory($this);
        $this->hashtagsService->processHashTagForCategory($this);
    }
}
