<?php declare(strict_types=1);

namespace common\components\scrapers\dto;

use common\models\Article;

class ArticleBodyNodeVideoPreview extends ArticleBodyNode
{
    public function __construct(string $previewUrl, string $videoUrl)
    {
        parent::__construct(Article::BODY_PART_VIDEO_PREVIEW, [
            'previewUrl' => $previewUrl,
            'videoUrl' => $videoUrl
        ]);
    }
}