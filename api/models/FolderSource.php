<?php declare(strict_types=1);

namespace api\models;

class FolderSource extends \common\models\Source
{
    public function fields()
    {
        $fields = parent::fields();
        $fields[] = 'urls';
        return $fields;
    }
}