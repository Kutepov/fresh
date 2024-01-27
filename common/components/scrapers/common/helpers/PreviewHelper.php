<?php
namespace common\components\scrapers\common\helpers;

use common\components\scrapers\common\services\HashImageService;
use Symfony\Component\DomCrawler\Crawler;

class PreviewHelper
{
    /**
     * @var HashImageService
     */
    private $hashImageService;

    public function __construct(HashImageService $hashImageService)
    {
        $this->hashImageService = $hashImageService;
    }

    public function getOgImageUrlHash(Crawler $html, string $placeholderTemplate = '', string $filter = "@property='og:image'"): ?string
    {
        $hashPreview = null;
        $previewImgNode = $html->filterXPath("//head//meta[".$filter."]")->first();
        if ($previewImgNode->count() > 0) {
            $previewImg = $previewImgNode->attr('content');
            $isPlaceholder = false;
            if ($placeholderTemplate !== '') {
                $isPlaceholder = stripos($previewImg, $placeholderTemplate) !== false;
            }

            if (!$isPlaceholder) {
                $hashPreview = $this->hashImageService->hashImage($previewImg);
            }
        }
        return $hashPreview;
    }

    public function getImageUrlHashFromList(Crawler $html, $filter = "//img", $attr = 'src', $baseUrl = '', string $placeholderTemplate = ''): ?string
    {
        $imgNode = $html->filterXPath($filter)->first();
        if (!$imgNode->count()) {
            return '';
        }

        if (is_array($attr)) {
            foreach ($attr as $item) {
                $imgUrl = $imgNode->attr($item);
                if (!is_null($imgUrl)) {
                    break;
                }
            }
        }
        else {
            $imgUrl = $imgNode->attr($attr);
        }

        if (is_null($imgUrl)) {
            return  '';
        }
        if (!filter_var($imgUrl, FILTER_VALIDATE_URL) && $baseUrl !== '') {
            $imgUrl = $baseUrl.$imgUrl;
        }

        if ($placeholderTemplate !== '' && stripos($imgUrl, $placeholderTemplate) !== false) {
            return '';
        }

        return $this->hashImageService->hashImage(
            $imgUrl
        );
    }
}