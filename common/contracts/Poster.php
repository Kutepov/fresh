<?php namespace common\contracts;

use common\models\Article;

interface Poster
{
    public function postArticle(Article $article): void;

    public function approveRequests(): void;
}