<?php namespace common\models\pivot;

use Carbon\Carbon;
use common\contracts\RatingObject;
use Yii;
use common\models\Comment;
use yii\behaviors\TimestampBehavior;


/**
 * This is the model class for table "comments_rating".
 *
 * @property string $created_at
 * @property string $updated_at
 * @property integer $comment_id
 * @property integer $app_id
 * @property integer $rating
 * @property string $country
 *
 * @property Comment $comment
 */
class CommentRating extends \yii\db\ActiveRecord implements RatingObject
{
    public const PLUS = 1;
    public const MINUS = -1;
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'comments_rating';
    }

    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'value' => Carbon::now('UTC')
            ]
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['created_at', 'updated_at'], 'safe'],
            [['comment_id', 'app_id'], 'required'],
            [['comment_id', 'app_id', 'rating'], 'integer'],
            [['comment_id', 'app_id'], 'unique', 'targetAttribute' => ['comment_id', 'app_id']],
            [['comment_id'], 'exist', 'skipOnError' => true, 'targetClass' => Comment::class, 'targetAttribute' => ['comment_id' => 'id']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'comment_id' => 'Comment ID',
            'rating' => 'Rating',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getComment()
    {
        return $this->hasOne(Comment::class, ['id' => 'comment_id']);
    }

    public static function find()
    {
        return (new \common\queries\CommentRating(get_called_class()));
    }

    public function getRatingValue(): int
    {
        return $this->rating;
    }
}
