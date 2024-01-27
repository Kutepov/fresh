<?php namespace common\components\db;

class Command extends \yii\db\Command
{
    public function insertIgnore($table, $columns, $onDuplicateKeyUpdate = [])
    {
        $sql = $this->insert($table, $columns)->getRawSql();
        $sql = preg_replace('#^INSERT INTO#siu', 'INSERT IGNORE INTO', $sql);
        if ($onDuplicateKeyUpdate) {
            $sql .= ' ON DUPLICATE KEY UPDATE ';
            foreach ($onDuplicateKeyUpdate as $column) {
                $sql .= "$column = VALUES($column),";
            }
            $sql = trim($sql, ',');
        }
        $this->setRawSql($sql);
        return $this;
    }

    public function batchInsertIgnore($table, $columns, $rows)
    {
        $sql = $this->batchInsert($table, $columns, $rows)->getRawSql();
        $sql = preg_replace('#^INSERT INTO#siu', 'INSERT IGNORE INTO', $sql);
        $this->setRawSql($sql);
        return $this;
    }

    public function batchInsertIgnoreFromArray(string $table, array $rows)
    {
        $rows = array_values($rows);

        if (!count($rows)) {
            throw new \Exception('Rows array is empty.');
        }

        $values = array_map(static function ($row) {
            return array_values($row);
        }, $rows);

        return $this->batchInsertIgnore($table, array_keys($rows[0]), $values);
    }
}