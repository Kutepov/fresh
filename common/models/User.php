<?php

namespace common\models;

use Carbon\Carbon;
use common\behaviors\CarbonBehavior;
use common\components\helpers\Api;
use common\components\validators\TimestampValidator;
use LasseRafn\InitialAvatarGenerator\InitialAvatar;
use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\web\IdentityInterface;

/**
 * User model
 *
 * @property integer $id
 * @property string $password_hash
 * @property int $password_exists
 * @property string $password_reset_token
 * @property string $verification_token
 * @property string $email
 * @property string $auth_key
 * @property integer $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property string $password write-only password
 * @property string $useragent
 * @property string $country_code
 * @property string $language_code
 * @property string $geo
 * @property string $ip
 * @property string $access_token
 * @property string $name
 * @property string $photo
 * @property integer $rating
 * @property string $platform
 * @property boolean $shadow_ban
 *
 * @property-read App[] $apps
 * @property-read App[] $appsWithSubscription
 *
 * @property-read \common\models\Comment[] $comments
 * @property-read \common\models\UserSocial[] $oauthAccounts
 * @property-read bool $isIos
 * @property-read bool $isAndroid
 * @property-read string|null $photoUrl
 * @property-read string $avatarUrl
 */
class User extends ActiveRecord implements IdentityInterface
{
    public const STATUS_DELETED = -1;
    public const STATUS_INACTIVE = 0;
    public const STATUS_ACTIVE = 1;
    public const STATUS_BANNED = -2;

    public const SCENARIO_API_AUTH = 'apiAuth';

    public const PLATFORM_IOS = App::PLATFORM_IOS;
    public const PLATFORM_ANDROID = App::PLATFORM_ANDROID;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'users';
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            [
                'class' => CarbonBehavior::class,
                'attributes' => ['created_at', 'updated_at'],
            ],
            [
                'class' => TimestampBehavior::class,
                'value' => Carbon::now('UTC')
            ]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            ['status', 'default', 'value' => self::STATUS_INACTIVE],
            ['platform', 'in', 'range' => [self::PLATFORM_IOS, self::PLATFORM_ANDROID]],
            ['status', 'in', 'range' => [self::STATUS_ACTIVE, self::STATUS_INACTIVE, self::STATUS_DELETED, self::STATUS_BANNED]],
            [['created_at', 'updated_at'], TimestampValidator::class],
            ['shadow_ban', 'boolean']
        ];
    }

    public function scenarios()
    {
        return ArrayHelper::merge(parent::scenarios(), [
            self::SCENARIO_API_AUTH => parent::scenarios()[self::SCENARIO_DEFAULT]
        ]);
    }

    public function fields()
    {
        $fields = [
            'id',
            'name',
            'photo' => 'photoUrl',
            'rating'
        ];

        if (Api::version(Api::V_2_07)) {
            $fields['commentsAmount'] = 'comments_amount';
        }

        if ($this->scenario === self::SCENARIO_API_AUTH) {
            $fields['accessToken'] = 'access_token';
            $fields[] = 'email';
            $fields['passwordExists'] = function () {
                return (bool)$this->password_exists;
            };

            $fields['confirmed'] = function () {
                return $this->status === self::STATUS_ACTIVE;
            };

            $fields['oauthAccounts'] = function () {
                return ArrayHelper::getColumn($this->oauthAccounts, 'source');
            };
        }

        if (Api::version(Api::V_2_21)) {
            $fields['pro'] = function () {
                if ($this->id == 1439) {
                    return true;
                }
                return count($this->appsWithSubscription) > 0;
            };
        }

        return $fields;
    }

    /**
     * {@inheritdoc}
     */
    public static function findIdentity($id)
    {
        return static::findOne(['id' => $id, 'status' => self::STATUS_ACTIVE]);
    }

    /**
     * {@inheritdoc}
     */
    public static function findIdentityByAccessToken($token, $type = null): ?self
    {
        return self::findOne(['access_token' => $token]);
    }

    /**
     * Finds user by password reset token
     *
     * @param string $token password reset token
     * @return static|null
     */
    public static function findByPasswordResetToken($token)
    {
        if (!static::isPasswordResetTokenValid($token)) {
            return null;
        }

        return static::findOne([
            'password_reset_token' => $token,
            'status' => self::STATUS_ACTIVE,
        ]);
    }

    /**
     * Finds user by verification email token
     *
     * @param string $token verify email token
     * @return static|null
     */
    public static function findByVerificationToken($token)
    {
        return static::findOne([
            'verification_token' => $token,
            'status' => self::STATUS_INACTIVE
        ]);
    }

    public static function findById($id): ?self
    {
        return self::findOne(['id' => $id]);
    }

    public static function findByEmail(string $email): ?self
    {
        if (!trim($email)) {
            return null;
        }

        return self::findOne(['email' => $email]);
    }

    /**
     * Finds out if password reset token is valid
     *
     * @param string $token password reset token
     * @return bool
     */
    public static function isPasswordResetTokenValid($token)
    {
        if (empty($token)) {
            return false;
        }

        $timestamp = (int)substr($token, strrpos($token, '_') + 1);
        $expire = Yii::$app->params['user.passwordResetTokenExpire'];
        return $timestamp + $expire >= time();
    }

    /**
     * {@inheritdoc}
     */
    public function getId()
    {
        return $this->getPrimaryKey();
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthKey()
    {
        return $this->auth_key;
    }

    /**
     * {@inheritdoc}
     */
    public function validateAuthKey($authKey)
    {
        return $this->getAuthKey() === $authKey;
    }

    /**
     * Validates password
     *
     * @param string $password password to validate
     * @return bool if password provided is valid for current user
     */
    public function validatePassword($password)
    {
        return Yii::$app->security->validatePassword($password, $this->password_hash);
    }

    /**
     * Generates password hash from password and sets it to the model
     *
     * @param string $password
     */
    public function setPassword($password)
    {
        $this->password_hash = Yii::$app->security->generatePasswordHash($password);
    }

    /**
     * Generates "remember me" authentication key
     */
    public function generateAuthKey()
    {
        $this->auth_key = Yii::$app->security->generateRandomString();
    }

    public function generateAccessToken()
    {
        $this->access_token = Yii::$app->security->generateRandomString();
    }

    /**
     * Generates new password reset token
     */
    public function generatePasswordResetToken()
    {
        $this->password_reset_token = Yii::$app->security->generateRandomString() . '_' . time();
    }

    /**
     * Generates new token for email verification
     */
    public function generateEmailVerificationToken()
    {
        $this->verification_token = Yii::$app->security->generateRandomString() . '_' . time();
    }

    /**
     * @throws \Exception
     */
    public function generateConfirmCode()
    {
        $this->verification_token = random_int(100000, 999999);
    }

    /**
     * Removes password reset token
     */
    public function removePasswordResetToken()
    {
        $this->password_reset_token = null;
    }

    public function getPhotoUrl(): ?string
    {
        if (!$this->photo) {
            return null;
        }

        return 'https://api.myfresh.app/' . $this->photo;
    }

    public function getAvatarUrl()
    {
        return $this->photoUrl ?: Url::to(['users/avatar', 'id' => $this->id], true);
    }

    public function generateAvatar()
    {
        $avatar = new InitialAvatar();
        return $avatar->name(mb_substr($this->name, 0, 1))
            ->height(56)
            ->background('#2bc08b')
            ->color('#ffffff')
            ->generate()
            ->stream('jpg');
    }

    public function getOauthAccounts(): \yii\db\ActiveQuery
    {
        return $this->hasMany(UserSocial::class, [
            'user_id' => 'id'
        ]);
    }

    public function getComments(): \yii\db\ActiveQuery
    {
        return $this->hasMany(Comment::class, [
            'user_id' => 'id'
        ]);
    }

    public function getIsIos(): bool
    {
        return $this->platform === self::PLATFORM_IOS;
    }

    public function getIsAndroid(): bool
    {
        return $this->platform === self::PLATFORM_ANDROID;
    }

    public function getApps()
    {
        return $this->hasMany(App::class, [
            'user_id' => 'id'
        ]);
    }

    public function getAppsWithSubscription()
    {
        return $this->getApps()->andWhere(['pro' => 1]);
    }
}
