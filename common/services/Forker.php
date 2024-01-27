<?php namespace common\services;

class Forker
{
    private $processesCount = 0;

    private $connectionsManager;

    public function __construct(ConnectionsManager $connectionsManager)
    {
        $this->connectionsManager = $connectionsManager;
    }

    public function invoke(callable $callable, int $maxProcessesCount = 1): void
    {
        $this->connectionsManager->closeAllConnections();
        switch ($pid = pcntl_fork()) {
            case -1:
                die('fork failed');

            case 0:
                $this->connectionsManager->reopenAllConnections();
                try {
                    $callable();
                } catch (\Throwable $e) {
                    $this->processesCount--;
                    throw $e;
                }
                $this->processesCount--;
                exit;

            default:
                $this->processesCount++;
                if ($this->processesCount >= $maxProcessesCount) {
                    pcntl_wait($status);
                }
                break;
        }
    }

    public function wait()
    {
        while (pcntl_waitpid(0, $status) != -1);
        $this->connectionsManager->openAllConnections();
    }
}