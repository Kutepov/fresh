<?php namespace backend\models;

use Carbon\Carbon;
use common\components\caching\Cache;
use common\models\pivot\CountryLanguage;
use yii\behaviors\TimestampBehavior;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
use yii\web\UploadedFile;
use Yii;

class Country extends \common\models\Country
{
    public const PREVIEW_TYPES_SELECTBOX = [
        self::PREVIEW_TYPE_SMALL => 'Маленькие картинки',
        self::PREVIEW_TYPE_BIG => 'Большие картинки'
    ];

    public const SCENARIO_UPDATE = 'update';
    /**
     * @var array
     */
    public $languages = [];

    /**
     * @var UploadedFile
     */
    public $imageFile;

    /**
     * @return array
     */
    public function rules()
    {
        $rules = parent::rules();
        $rules[] = [['imageFile'], 'file', 'skipOnEmpty' => false, 'extensions' => 'png, jpg', 'except' => self::SCENARIO_UPDATE];
        $rules[] = [['languages'], 'validateLanguages'];
        $rules[] = ['articles_preview_type', 'in', 'range' => self::PREVIEW_TYPES];
        $rules[] = ['articles_preview_type_switcher', 'boolean'];
        $rules[] = ['quality_survey', 'boolean'];
        return $rules;
    }

    public function behaviors()
    {
        return ArrayHelper::merge(parent::behaviors(), [
            [
                'class' => TimestampBehavior::class,
                'value' => static function () {
                    return Carbon::now()->toDateTimeString();
                }
            ]
        ]);
    }

    public function validateLanguages($attribute, $params)
    {
        $items = $this->$attribute;

        if (!is_array($items)) {
            $items = [];
        }

        $setDefaults = false;
        foreach ($items as $index => $item) {
            $language = Language::findOne($item['id']);
            if (is_null($language)) {
                $language = new Language();
            }
            $language->scenario = Language::SCENARIO_VIA_COUNTRY_FORM;
            $language->load($item, '');
            $language->validate();

            if ($item['default']) {
                $setDefaults = true;
            }

            foreach ($language->errors as $attr => $messages) {
                foreach ($messages as $message) {
                    $this->addError($attribute . '[' . $index . '][' . $attr . ']', $message);
                }
            }
        }

        if (!$setDefaults) {
            $this->addError($attribute . '[0][default]', 'Необходимо указать');
        }
    }

    public function attributeLabels(): array
    {
        $labels = parent::attributeLabels();
        $labels['imageFile'] = 'Флаг';
        $labels['languages'] = 'Языки';
        return $labels;
    }

    public function afterFind()
    {
        foreach ($this->countryLanguages as $countryLanguage) {
            $this->languages[] = [
                'id' => $countryLanguage->language_id,
                'code' => $countryLanguage->language->code,
                'short_name' => $countryLanguage->language->short_name,
                'default' => $countryLanguage->default,
            ];
        }
        parent::init();
    }

    public function upload()
    {
        if ($this->validate()) {
            $this->imageFile->saveAs(
                Yii::getAlias('@api') . '/web/img/country/' .
                $this->code . '.' .
                $this->imageFile->extension
            );
            return true;
        }
        else {
            return false;
        }
    }

    /**
     * @return array
     */
    public static function getTimezonesForDropdown()
    {
        $timezones = timezone_identifiers_list(\DateTimeZone::ALL_WITH_BC);
        $result = [];
        foreach ($timezones as $zone) {
            $result[$zone] = $zone;
        }
        return $result;
    }

    /**
     * @return string
     */
    public function getAbsoluteUrlToImage()
    {
        return Yii::getAlias('@frontendBaseUrl') . '/img/' . $this->image;
    }

    public function save($runValidation = true, $attributeNames = null)
    {
        $this->imageFile = UploadedFile::getInstance($this, 'imageFile');
        if ($this->imageFile instanceof UploadedFile) {
            $this->deleteImage();
            $this->image = $this->imageFile instanceof UploadedFile ? 'country/' . $this->code . '.' . $this->imageFile->extension : '';
        }
        if (parent::save($runValidation, $attributeNames)) {
            if ($this->imageFile instanceof UploadedFile) {
                $this->upload();
            }
            $this->saveLanguages($this->languages);
            return true;
        }
        return false;
    }

    private function saveLanguages($languages)
    {
        CountryLanguage::deleteAll(['country_id' => $this->id]);
        if (is_array($languages)) {
            foreach ($languages as $language) {
                $languageModel = Language::findOne($language['id']);
                if (!$languageModel) {
                    $languageModel = new Language();
                    $languageModel->name = $language['id'];
                }
                $languageModel->code = $language['code'];
                $languageModel->short_name = $language['short_name'];
                $languageModel->save();

                $countryLanguage = new CountryLanguage();
                $countryLanguage->country_id = $this->id;
                $countryLanguage->language_id = $languageModel->id;
                $countryLanguage->default = $language['default'];
                $countryLanguage->save();
            }
        }
    }

    private function deleteImage()
    {
        $pathToFile = Yii::getAlias('@api') . '/web/img/' . $this->image;
        if (is_file($pathToFile)) {
            FileHelper::unlink($pathToFile);
        }
    }

    public function afterSave($insert, $changedAttributes)
    {
        if ($insert) {
            $this->createDefaults();
        }
        Cache::clearByTag(Cache::TAG_COUNTRIES_LIST);
        parent::afterSave($insert, $changedAttributes);
    }

    private function createDefaults()
    {
        AdProvider::createDefaultsForCountry($this->code);
        AdBanner::createDefaultsForCountry($this->code);
        CategoryCountry::createDefaultsForCountry($this->code);
    }

    public function delete()
    {
        $this->deleteImage();
        return parent::delete();
    }

    public static function getForDropdown($indexById = false)
    {
        return self::find()->indexBy($indexById ? 'id' : 'code')->select('name')->column();
    }

}
