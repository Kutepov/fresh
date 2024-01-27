<?php namespace common\services;

use yii;

class DbManager
{
    private $unbufferedConnection;

    public function wrap(callable $function)
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $result = $function();
            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }

        return $result ?? null;
    }

    /**
     * @param callable $function
     * @param int $maxRetries
     * @param int $retriesCount
     * @throws \Throwable
     */
    public function executeWithRetries(callable $function, $maxRetries = 5, callable $onException = null, &$retriesCount = 0): void
    {
        try {
            $retriesCount++;
            $function();
        } catch (\Throwable $e) {
            if ($onException !== null) {
                $onException($e);
            }

            if (
                $retriesCount < $maxRetries &&
                (
                    in_array($e->getCode(), [1205, 1213], true) ||
                    stripos($e->getMessage(), 'try restarting transaction') !== false
                )
            ) {
                $this->executeWithRetries($function, $maxRetries, $onException, $retriesCount);
            }
            else {
                throw $e;
            }
        }
    }

    public function getUnbufferedConnection($db = null, $db_identifier = null)
    {
        if (is_null($this->unbufferedConnection)) {
            if (is_null($db)) {
                $db = Yii::$app->db;
            }

            $db_string = '';
            if (is_string($db)) { //TO SUPPORT the $db of Component Definition ID passed in string  ,for example $db='db'
                $db_string = $db;
                if (empty($db_identifier)) {
                    $db_identifier = $db;
                }
                $db = Yii::$app->get($db); // Convert string Component Definition ID to a Component
            }
            if (!($db instanceof \yii\db\Connection) || !strstr($db->getDriverName(), 'mysql')) { //Safe Check
                throw  new yii\base\InvalidParamException('Not a Mysql Component');
            };
            if (empty($db_identifier)) { //Generate a New String Component Definition ID if $db_identifier is not Provided
                $db_identifier = md5(sprintf('%s%s%s%s',
                    $db->dsn,
                    $db->username,
                    $db->password,
                    var_export($db->attributes, true)
                ));
            }
            $db_identifier = 'unbuffered_' . $db_identifier;
            if (!Yii::$app->has($db_identifier)) {
                if ($db_string) {
                    $_unbuffered_db = Yii::$app->getComponents()[$db_string];//Clone a Configuration 、、克隆一个配置
                    $_unbuffered_db['attributes'][\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = false;
                }
                else {
                    $_ = clone $db;
                    $_->close(); //Ensure that it is not an active Connection Because PDO can not be serialize
                    /** @var  $_unbuffered_db \yii\db\Connection */
                    $_unbuffered_db = unserialize(serialize($_)); //Clone a Expensive Object //deep copy for safe
                    $_unbuffered_db->attributes[\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = false;
                }
                Yii::$app->setComponents([$db_identifier => $_unbuffered_db]);
            }

            $this->unbufferedConnection = Yii::$app->get($db_identifier);
        }


        return $this->unbufferedConnection;
    }
}