<?php

namespace PayrexxPaymentGateway\ScheduledTasks;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Shopware\Core\Framework\Context;

class CancelOrderScheduledTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'payrexx.cancel_order_task';
    }

    public static function getInterval(): int
    {
        return 30; // Run the task every 60 seconds (1 minute)
    }

    public function getHandler(): string
    {
        return CancelOrdersTaskHandler::class;
    }
}