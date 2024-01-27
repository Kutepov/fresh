<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Class m220826_112306_fix_timezones_in_sources_urls_table
 */
class m220826_112306_fix_timezones_in_sources_urls_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $badTimezones = (new Query())->from('{{%sources_urls}}')->where('timezone REGEXP "^[0-9]+$"')->all();

        $sourceId = null;
        foreach ($badTimezones as $row) {
            if ($row['source_id'] !== $sourceId) {
                $sourceId = $row['source_id'];
                $sourceTimezone = (new Query())->select('timezone')->from('{{%sources}}')->where(['id' => $sourceId])->one();
                if (!$sourceTimezone) {
                    continue;
                }
            }

            $this->update('{{%sources_urls}}', ['timezone' => $sourceTimezone['timezone']], ['id' => $row['id']]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m220826_112306_fix_timezones_in_sources_urls_table cannot be reverted.\n";

        return false;
    }
}
