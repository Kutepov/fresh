<?php declare(strict_types=1);

namespace common\services;

use common\models\Category;
use common\models\Hashtag;
use common\models\SourceUrl;

class HashtagsService
{
    private const HASHTAG_REGEXP = '/^#([\p{L}\w]+)$/iu';

    public function clear($hashtag): string
    {
        return trim($hashtag, '# ');
    }

    public function isValidHashtag(string $string): bool
    {
        return (bool)preg_match(self::HASHTAG_REGEXP, trim($string));
    }

    public function processHashTagForCategory(Category $category)
    {
        foreach ($category->languagesPivot as $translation) {
            if (!$translation->title && $translation->hashtag) {
                $translation->updateAttributes([
                    'hashtag_id' => null
                ]);
            } elseif ($translation->title && !$translation->hashtag) {
                $expectedHashtag = convertToHashTag($translation->title);
                if (!($hashTag = Hashtag::findByTag($expectedHashtag))) {
                    $hashTag = new Hashtag(['tag' => $expectedHashtag]);
                    $hashTag->save();
                }

                $translation->updateAttributes([
                    'hashtag_id' => $hashTag->id
                ]);
            }
        }
    }

    public function processHashTagsForSourceUrl(SourceUrl $sourceUrl)
    {

    }

}