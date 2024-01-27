<?php namespace common\components\scrapers\services;

use common\components\scrapers\common\exceptions\ParsingResultException;
use common\components\scrapers\dto\ArticleBody;
use common\components\scrapers\dto\ArticleBodyNode;
use common\models\Article;

class ParsingResultValidator
{
    /**
     * @param ArticleBody $body
     * @return bool
     * @throws ParsingResultException
     */
    public function validateArticleBody(ArticleBody $body): bool
    {
        if (!count($body->getNodes())) {
            throw new ParsingResultException('Article body is empty.');
        }

        foreach ($body->getNodes() as $node) {
            if (
                in_array($node->getElementName(), Article::BODY_PARTS_WITH_URLS) &&
                !trim($node->getValue())
            ) {
                throw new ParsingResultException('Article body part ' . $node->getElementName() . ' value is empty');
            }
        }

        $textNodesCount = array_filter($body->getNodes(), static function (ArticleBodyNode $node) {
            return in_array($node->getElementName(), [
                Article::BODY_PART_PARAGRAPH,
                Article::BODY_PART_RICH_PARAGRAPH,
                Article::BODY_PART_UL,
                Article::BODY_PART_OL,
            ], true);
        });

        if (!$textNodesCount) {
            return false;
        }

        return true;
    }

    /**
     * @param ArticleBody $description
     * @return bool
     * @throws ParsingResultException
     */
    public function validateArticleDescription(ArticleBody $description): bool
    {
        if (!count($description->getNodes())) {
            throw new ParsingResultException('Article description is empty.');
        }

        return true;
    }


}