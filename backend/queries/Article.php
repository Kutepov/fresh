<?php namespace backend\queries;

/**
 * Class Article
 * @package backend\queries
 *
 * @see \backend\models\Article
 */
class Article extends \common\queries\Article
{
    public function one($db = null, $skipSameArticles = false)
    {
        return parent::one($db, $skipSameArticles);
    }

    public function count($q = '*', $db = null, $skipSameArticles = false)
    {
        return parent::count($q, $db, $skipSameArticles);
    }

    public function all($db = null, $skipSameArticles = false)
    {
        return parent::all($db, $skipSameArticles);
    }
}