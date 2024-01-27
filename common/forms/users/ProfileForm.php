<?php namespace common\forms\users;

use common\models\User;
use yii\base\Model;

/**
 * Class ProfileForm
 * @package common\forms\users
 * @property string $name
 * @property \yii\web\UploadedFile $photo
 */
class ProfileForm extends Model
{
    public const SCENARIO_UPLOAD_PHOTO = 'uploadPhoto';
    public const SCENARIO_DELETE_PHOTO = 'deletePhoto';
    public const SCENARIO_DELETE_PROFILE = 'deleteProfile';

    public $name;
    public $photo;

    public function scenarios(): array
    {
        return [
            self::SCENARIO_DEFAULT => ['name'],
            self::SCENARIO_UPLOAD_PHOTO => ['photo'],
            self::SCENARIO_DELETE_PHOTO => [],
            self::SCENARIO_DELETE_PROFILE => []
        ];
    }

    public function rules(): array
    {
        return [
            ['name', 'trim'],
            ['name', 'required'],
            ['name', 'string', 'max' => 64],
            [
                'photo',
                'image',
                'skipOnEmpty' => false,
                'mimeTypes' => ['image/jpeg', 'image/png'],
                'extensions' => ['jpg', 'png', 'jpeg'],
                'maxSize' => 1024 * 1024 * 10,
                'tooBig' => \t('Изображение слишком большое'),
                'wrongExtension' => \t('Неверный формат изображения'),
                'wrongMimeType' => \t('Неверный формат изображения'),
                'uploadRequired' => \t('Изображение не выбрано')
            ],
        ];
    }

    public function getUser(): ?User
    {
        return \Yii::$app->user->identity;
    }

    public function attributeLabels(): array
    {
        return [
            'name' => \t('Имя'),
            'photo' => \t('Фото')
        ];
    }

    public function formName(): string
    {
        return '';
    }
}