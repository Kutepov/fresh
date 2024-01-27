<?php namespace common\components\queue;

class Queue extends \yii\queue\amqp_interop\Queue
{
    public function init(): void
    {
        parent::init();

        if (extension_loaded('pcntl') && PHP_MAJOR_VERSION >= 7) {
            // https://github.com/php-amqplib/php-amqplib#unix-signals
            $signals = [SIGTERM, SIGQUIT, SIGINT, SIGHUP];

            foreach ($signals as $signal) {
                $oldHandler = null;
                // This got added in php 7.1 and might not exist on all supported versions
                if (function_exists('pcntl_signal_get_handler')) {
                    $oldHandler = pcntl_signal_get_handler($signal);
                }

                pcntl_signal($signal, static function ($signal) use ($oldHandler) {
                    if ($oldHandler && is_callable($oldHandler)) {
                        $oldHandler($signal);
                    }

                    pcntl_signal($signal, SIG_DFL);
                    posix_kill(posix_getpid(), $signal);
                });
            }
        }
    }

    public function closeConnection()
    {
        $this->close();
    }

    public function openConnection()
    {
        $this->open();
    }
}