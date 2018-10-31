<?php

namespace LightStepBase\Client;

use Psr\Log\AbstractLogger;

class SystemLogger extends AbstractLogger
{

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function log($level, $message, array $context = [])
    {
        error_log($message . PHP_EOL . var_export($context, true));
    }
}
