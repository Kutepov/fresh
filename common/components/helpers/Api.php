<?php namespace common\components\helpers;

use yii;

class Api
{
    /** Новая версия приложения */
    public const V_2_0 = '2.0';
    /**
     * В модель Country добавлено свойство articlesLanguages: ?array с массивом моделей Language;
     * Добавлен новый заголовок X-Api-Articles-Language для выбора языка новостей;
     * В модель Source добавлено свойство group_id: ?string для идентификации нескольких одинаковых источников с разными языками.
     */
    public const V_2_01 = '2.01';

    /**
     * В модели Source свойство domain заменено на url.
     */
    public const V_2_02 = '2.02';

    /**
     * Авторизация;
     * Комментарии;
     * Рейтинг статьи;
     * URL для шеринга статьи;
     * jsInjection.
     */
    public const V_2_03 = '2.03';

    /**
     * Банер в теле новости
     */
    public const V_2_04 = '2.04';

    /**
     * Полноэкранный банер
     */
    public const V_2_05 = '2.05';

    /**
     * Новости-превью с одним параграфом;
     * Банеры в "читать также"
     */
    public const V_2_06 = '2.06';

    /**
     * Количество комментов юзера
     */
    public const V_2_07 = '2.07';

    /**
     * Смена внешнего вида превью новостей для разных стран
     */
    public const V_2_08 = '2.08';

    /**
     * Видео-новости
     */
    public const V_2_09 = '2.09';

    /**
     * Удаление и редактирование комментариев
     */
    public const V_2_10 = '2.10';

    /**
     * Новые типы новостей - youtube, twitter, reddit
     * Каталог источников
     * Папки
     * Комменты в источниках (вкл/выкл)
     * alias в sourceUrl
     */
    public const V_2_20 = '2.20';

    /**
     * Превью ютуба с плеером
     */
    public const V_2_21 = '2.21';

    /**
     * Значение "editable" у коммента
     */
    public const V_2_22 = '2.22';

    public const V_2_23 = '2.23';

    /** Возможные заголовки при запросах к API */
    public const API_HEADER_LANGUAGE = 'X-Api-Language';
    public const API_HEADER_ARTICLES_LANGUAGE = 'X-Api-Articles-Language';
    public const API_HEADER_COUNTRY = 'X-Api-Country';
    public const API_HEADER_PLATFORM = 'X-Api-Platform';
    public const API_HEADER_APP_VERSION = 'X-Api-App-Version';
    public const API_HEADER_UID = 'X-Api-DeviceId';
    public const API_HEADER_ARTICLES_PREVIEW_TYPE = 'X-Api-Articles-Preview-Type';

    public const OP_GREATER_THAN_OR_EQUAL = '>=';
    public const OP_LESS_THAN = '<';
    public const OP_EQUAL = '=';
    public const OP_NOT_EQUAL = '!=';

    public static function isRequestFromApp(): bool
    {
        return self::version('9999999', self::OP_NOT_EQUAL);
    }

    public static function versionLessThan($needVersion): bool
    {
        return self::version($needVersion, self::OP_LESS_THAN);
    }

    public static function version($needVersion = null, $operator = self::OP_GREATER_THAN_OR_EQUAL): bool
    {
        $version = defined('API_VERSION') ? API_VERSION : (Yii::$app->response->acceptParams['version'] ?? false);

        if (!$version && preg_match('#version=(v[\d\.]+)$#', $_SERVER['HTTP_ACCEPT'], $m)) {
            $version = $m[1];
        }

        if ($version) {
            if ($version[0] === 'v') {
                $version = substr($version, 1);
            }
        } else {
            $version = '1.0';
        }

        if (strlen($needVersion) === 3) {
            $needVersion .= '0';
        }

        if (strlen($version) === 3) {
            $version .= '0';
        }

        if (is_null($needVersion)) {
            return $version;
        }

        return version_compare($version, (string)$needVersion, $operator);
    }

    public static function getHeaderValue($header)
    {
        return Yii::$app->request->headers->get($header);
    }
}