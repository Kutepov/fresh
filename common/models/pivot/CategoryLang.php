<?php declare(strict_types=1);

namespace common\models\pivot;

use common\models\Hashtag;
use yii\db\ActiveRecord;

/**
 * @property string $owner_id
 * @property string $language
 * @property string $title
 * @property string $hashtag_id
 *
 * @property-read Hashtag|null $hashtag
 */
class CategoryLang extends ActiveRecord
{
    public static function tableName()
    {
        return 'categories_lang';
    }

    public function getHashtag()
    {
        return $this->hasOne(Hashtag::class, [
            'id' => 'hastag_id'
        ]);
    }
}