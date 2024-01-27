<?php declare(strict_types=1);

namespace api\models;

use common\models\SourceUrl;

class FolderSourceUrl extends SourceUrl
{
    public function getSource()
    {
        return $this->hasOne(FolderSource::class, [
            'id' => 'source_id'
        ]);
    }
}