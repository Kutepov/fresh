<?php namespace backend\models;

use JMS\Serializer\Expression\Expression;
use Yii;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
use yii\web\UploadedFile;

/**
 * @property  string[] $countries_ids
 */
class Source extends \common\models\Source
{
    public function setDefaults()
    {
        $this->enabled = true;
        $this->webview_js = true;
        $this->default = true;
        $this->enable_comments = true;
        $this->ios_enabled = true;
        $this->android_enabled = true;
    }

    /**
     * @var UploadedFile
     */
    public $imageFile;

    public const TYPES = [
        self::TYPE_FULL_ARTICLE => 'Полные новости',
        self::TYPE_PREVIEW => 'Превью',
        self::TYPE_WEBVIEW => 'WebView',
        self::TYPE_YOUTUBE => 'YouTube',
        self::TYPE_YOUTUBE_PREVIEW => 'YouTube Превью',
        self::TYPE_BROWSER => 'Браузер',
        self::TYPE_TELEGRAM => 'Telegram',
        self::TYPE_TWITTER => 'Twitter',
        self::TYPE_REDDIT => 'Reddit'
    ];

    /**
     * @return array
     */
    public function rules(): array
    {
        return ArrayHelper::merge(parent::rules(), [
            [['imageFile'], 'file', 'skipOnEmpty' => true, 'extensions' => 'png, jpg'],
            ['injectable_css', 'string'],
            ['injectable_js', 'string'],
            ['copy_from_source_id', 'string'],
            ['adblock_css_selectors', 'string'],
            [['processed', 'telegram', 'push_notifications', 'default'], 'boolean'],
            ['telegram_channel_id', 'string'],
            ['telegram_channel_id', 'required', 'when' => function (Source $source) {
                return $source->telegram;
            }, 'whenClient' => 'function() {
                return $("#source-telegram").is(":checked");
            }'],
            ['countries_ids', 'default', 'value' => []],
            ['countries_ids', 'required', 'when' => function (Source $source) {
                return $source->default;
            }, 'whenClient' => 'function() {
                return $("#source-default").is(":checked");
            }'],
            ['country', 'string'],
            ['country', 'required', 'when' => function (Source $source) {
                return count($source->countries_ids) > 1;
            }, 'whenClient' => 'function() {
                return $("#source-countries_ids").val().length > 1;
            }'],
            ['country', 'default', 'value' => null],
            ['country', 'validateDefaultCountry'],
        ]);
    }

    public function validateDefaultCountry()
    {
        if ($this->default) {
            if (count($this->countries_ids) === 1) {
                $this->country = \common\models\Country::findOne($this->countries_ids[0])->code;
            }
            else {
                if (!in_array(\common\models\Country::findByCode($this->country)->id, $this->countries_ids)) {
                    $this->addError('country', 'Недопустимое значение');
                }
            }
        }
        else {
//            $this->countries_ids = [];
//            $this->country = null;
        }
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'enable_comments' => 'Включить комментарии',
            'name' => 'Название',
            'url' => 'Url',
            'image' => 'Изображение',
            'imageFile' => 'Изображение',
            'country' => 'Страна, локаль которой будет использоваться для индексирования',
            'countries_ids' => 'Страны',
            'countries' => 'Страны',
            'language' => 'Язык',
            'type' => 'Тип',
            'webview_js' => 'Включить JS в WebView',
            'banned_top' => 'Не выводить в топе',
            'urls' => 'Категории',
            'created_at' => 'Добавлено',
            'ios_enabled' => 'iOS',
            'android_enabled' => 'Android',
            'enabled' => 'Включен',
            'group_id' => 'Группировать с...',
            'note' => 'Заметка',
            'injectable_css' => 'Пользовательский CSS',
            'injectable_js' => 'Пользовательский JS',
            'adblock_css_selectors' => 'CSS селекторы для адблока',
            'processed' => 'Обработан',
            'telegram' => 'Постить новости в телеграм канал',
            'telegram_channel_id' => 'ID телеграм канала',
            'copy_from_source_id' => 'Копировать новости из источника',
            'push_notifications' => 'Отправлять PUSH-уведомления',
            'default' => 'Рекомендуемый',
            'use_publication_date' => 'Дата публикации вместо даты парсинга в новостях',
            'subscribers_count' => 'Подписки'
        ];
    }

    public function attributeHints(): array
    {
        return [
            'adblock_css_selectors' => 'Каждый с новой строки'
        ];
    }

    public function upload()
    {
        if ($this->validate()) {
            $this->imageFile->saveAs(
                Yii::getAlias('@api') . '/web/img/source/' .
                $this->id . '.' .
                $this->imageFile->extension
            );
            return true;
        }

        return false;
    }

    public function save($runValidation = true, $attributeNames = null)
    {
        $this->imageFile = UploadedFile::getInstance($this, 'imageFile');
        if ($this->imageFile instanceof UploadedFile) {
            $this->deleteImage();
            $this->image = $this->imageFile instanceof UploadedFile ? 'source/' . $this->id . '.' . $this->imageFile->extension : '';
        }
        $this->saveGroupId();

        if (parent::save($runValidation, $attributeNames)) {
            if ($this->imageFile instanceof UploadedFile) {
                $this->upload();
            }
            return true;
        }
        return false;
    }

    private function saveGroupId()
    {
        //Группировка
        if ($this->group_id && array_key_exists('group_id', $this->dirtyAttributes)) {
            $childSource = Source::findOne(['id' => $this->group_id]);
            if ($childSource->group_id) {
                $this->group_id = $childSource->group_id;
            } else {
                $childSource->group_id = $this->group_id;
                Yii::$app->db->createCommand()->update(self::tableName(), ['group_id' => $this->group_id], ['id' => $childSource->id])->execute();
            }
        }
        //Разгруппировка
        if (!$this->group_id && array_key_exists('group_id', $this->dirtyAttributes) && !$this->isNewRecord) {
            $childSource = Source::find()
                ->where(['group_id' => $this->oldAttributes['group_id']])
                ->andWhere(['not', ['id' => $this->id]])
                ->all();
            if (count($childSource) === 1) {
                $childSource[0]->group_id = null;
                Yii::$app->db->createCommand()->update(self::tableName(), ['group_id' => null], ['id' => $childSource[0]->id])->execute();
            }
        }

        return true;
    }

    private function deleteImage()
    {
        $pathToFile = Yii::getAlias('@api') . '/web/img/' . $this->image;
        if (is_file($pathToFile)) {
            FileHelper::unlink($pathToFile);
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
        return Yii::getAlias('@frontendBaseUrl') . '/img/' . $this->image;
    }

    public static function getForDropdownSimple($orderBy = 'created_at')
    {
        return self::find()->indexBy('id')->orderBy([$orderBy => SORT_DESC])->select('name')->column();
    }

    public function getAllSourcesForDropdown()
    {
        $sources = self::find()
            ->where([
                'AND',
                ['<>', 'id', $this->id],
                ['IS', 'copy_from_source_id', null]
            ])
            ->all();

        $result = [];

        foreach ($sources as $source) {
            $result[$source->id] = $source->name . ($source->language ? ' [' . $source->language . ']' : '');
        }

        return $result;
    }

    public function getForDropdown($orderBy = 'created_at')
    {
        $groups = self::find()
            ->select(['id', 'name', 'IFNULL(group_id, UUID()) as ' . 'group_id_1'])
            ->asArray()
            ->orderBy([$orderBy => SORT_DESC])
            ->groupBy('group_id_1')
            ->andWhere(['not', ['id' => $this->id]])
            ->all();

        if ($this->group_id) {
            foreach ($groups as $key => $group) {
                if ($group['group_id_1'] === $this->group_id) {
                    $groups[$key]['id'] = $this->group_id;
                    break;
                }
            }
        }

        return ArrayHelper::map($groups, 'id', 'name');
    }

    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            if ($this->language === '') {
                $this->language = null;
                $this->group_id = null;
            }

            $this->telegram_channel_id = trim($this->telegram_channel_id, ' @');

            return true;
        }

        return false;
    }

    public function getAvailableLanguagesForDropdown($withSelf = true)
    {
        $languages = Language::getForDropdown();
        if ($this->group_id) {
            $grouped = self::find()
                ->where(['group_id' => $this->group_id]);
            if ($withSelf) {
                $grouped = $grouped->andWhere(['not', ['id' => $this->id]]);
            }

            $grouped = $grouped->select('language')->column();
            foreach ($languages as $code => $language) {
                if (in_array($code, $grouped)) {
                    unset($languages[$code]);
                }
            }
        }

        return $languages;
    }

    public function getArticles()
    {
        return $this->hasMany(Article::class, [
            'source_id' => 'id'
        ]);
    }

    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);
        if (count($this->countries) === 1 && (!$this->country || $this->country !== $this->countries[0]->code)) {
            $this->updateAttributes(['country' => $this->countries[0]->code]);
        }
        if (isset($changedAttributes['enabled']) || isset($changedAttributes['ios_enabled']) || isset($changedAttributes['android_enabled'])) {
            foreach ($this->urls as $sourceUrl) {
                $sourceUrl->updateAttributes([
                    'enabled' => $this->enabled,
                    'ios_enabled' => $this->ios_enabled,
                    'android_enabled' => $this->android_enabled
                ]);
            }
        }
    }
}