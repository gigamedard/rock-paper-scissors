<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\HandlerWrapper;
use Monolog\LogRecord;

class OtherUserFilter
{
    public function __invoke(Logger $logger)
    {
        $trackedAddresses = [
            '0xf39fd6e51aad88f6f4ce6ab8827279cfffb92266',
            '0x70997970c51812dc3a010c7d01b50e0d17dc79c8',
            '0x3c44cdddb6a900fa2b585dd299e03d12fa4293bc',
            '0x90f79bf6eb2c4f870365e785982e1f101e93b906',
            '0x15d34aaf54267db7d7c367839aaf71a00a2c6a65',
            '0x9965507d1a55bcc2695c58ba16fb37d819b0a4dc',
        ];

        $monolog = $logger->getLogger();
        $handlers = $monolog->getHandlers();
        $newHandlers = [];

        foreach ($handlers as $handler) {
            $newHandlers[] = new class($handler, $trackedAddresses) extends HandlerWrapper
            {
                private $trackedAddresses;

                private $lineCount = 0;

                private const MAX_LINES = 50;

                public function __construct(HandlerInterface $handler, array $trackedAddresses)
                {
                    parent::__construct($handler);
                    $this->trackedAddresses = $trackedAddresses;
                }

                public function isHandling(LogRecord $record): bool
                {
                    if (! parent::isHandling($record)) {
                        return false;
                    }

                    $message = strtolower($record->message);

                    if (strpos($message, '0x') === false) {
                        return true;
                    }

                    foreach ($this->trackedAddresses as $address) {
                        if (strpos($message, $address) !== false) {
                            return false;
                        }
                    }

                    if (! empty($record->context)) {
                        $contextJson = strtolower(json_encode($record->context));
                        if (strpos($contextJson, '0x') !== false) {
                            foreach ($this->trackedAddresses as $address) {
                                if (strpos($contextJson, $address) !== false) {
                                    return false;
                                }
                            }
                        }
                    }

                    if ($this->lineCount >= self::MAX_LINES) {
                        return false;
                    }

                    $this->lineCount++;

                    return true;
                }

                public function handle(LogRecord $record): bool
                {
                    if (! $this->isHandling($record)) {
                        return false;
                    }

                    return $this->handler->handle($record);
                }
            };
        }

        $monolog->setHandlers($newHandlers);
    }
}
