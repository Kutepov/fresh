<?php namespace common\services;

use common\models\Article;
use yii\base\BaseObject;

class RestrictedWordsChecker extends BaseObject
{
    public $restrictedWords = [];
    public $bannedWords = [];

    public function findInArticle(Article $article): bool
    {
        return $this->findInString($article->title) || $this->findInString($article->bodyAsString);
    }

    public function findBannedWordsInArticle(Article $article): bool
    {
        return $this->findInString($article->title, true) || $this->findInString($article->bodyAsString, true);
    }

    public function findInString(?string $string = null, bool $bannedWords = false): bool
    {
        if (empty($string)) {
            return false;
        }

        $string = mb_strtolower($string);

        foreach (($bannedWords ? $this->bannedWords : $this->restrictedWords) as $restrictedWord) {
            if (mb_stripos($string, $restrictedWord) !== false) {
                return true;
            }
        }

        return false;
    }
}