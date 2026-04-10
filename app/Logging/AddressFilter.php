<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\HandlerWrapper;
use Monolog\LogRecord;

class AddressFilter
{
    /**
     * Customize the given logger instance.
     *
     * @return void
     */
    public function __invoke(Logger $logger)
    {
        // Allowed addresses (Hardhat accounts #1 to #6)
        $allowedAddresses = [
            // #1
            '0xf39Fd6e51aad88F6F4ce6aB8827279cffFb92266',
            // #2
            '0x70997970C51812dc3A010C7d01b50e0d17dc79C8',
            // #3
            '0x3C44CdDdB6a900fa2b585dd299e03d12FA4293BC',
            // #4
            '0x90F79bf6EB2c4f870365E785982E1f101E93b906',
            // #5
            '0x15d34AAf54267DB7D7c367839AAf71A00a2C6A65',
            // #6
            '0x9965507D1a55bcC2695C58ba16FB37d819B0A4dc',
        ];

        // Format to lowercase for case-insensitive comparison
        $allowedAddresses = array_map('strtolower', $allowedAddresses);

        $monolog = $logger->getLogger();
        $handlers = $monolog->getHandlers();
        $newHandlers = [];

        foreach ($handlers as $handler) {
            $newHandlers[] = new class($handler, $allowedAddresses) extends HandlerWrapper
            {
                private $allowedAddresses;

                public function __construct(HandlerInterface $handler, array $allowedAddresses)
                {
                    parent::__construct($handler);
                    $this->allowedAddresses = $allowedAddresses;
                }

                public function isHandling(LogRecord $record): bool
                {
                    if (! parent::isHandling($record)) {
                        return false;
                    }

                    $message = strtolower($record->message);

                    // Always allow application errors/exceptions that don't contain addresses
                    // Or, if strict filtering is needed: only allow logs WITH these addresses.
                    // Based on "seulemnt nos activiter soit logger", we filter out logs
                    // that contain `0x` but NOT these specific addresses.
                    // If a log DOES NOT contain `0x`, we let it pass (system errors, general info).

                    if (strpos($message, '0x') === false) {
                        return true;
                    }

                    // If it contains an address (0x...), check if it's one of ours
                    foreach ($this->allowedAddresses as $address) {
                        if (strpos($message, $address) !== false) {
                            return true;
                        }
                    }

                    // Also check context array
                    if (! empty($record->context)) {
                        $contextJson = strtolower(json_encode($record->context));
                        if (strpos($contextJson, '0x') !== false) {
                            foreach ($this->allowedAddresses as $address) {
                                if (strpos($contextJson, $address) !== false) {
                                    return true;
                                }
                            }
                        }
                    }

                    // Has 0x address but not in our allowed list -> Send to other_users.log
                    $this->logToOtherUsers($record);
                    return false;
                }

                private function logToOtherUsers(LogRecord $record)
                {
                    static $processedRecords = [];
                    $recordId = spl_object_id($record);
                    
                    if (isset($processedRecords[$recordId])) {
                        return; // Already wrote this record from another handler check
                    }
                    $processedRecords[$recordId] = true;

                    $logFile = storage_path('logs/other_users.log');
                    $date = $record->datetime->format('Y-m-d H:i:s');
                    $logEntry = "[{$date}] " . $record->message;

                    // Ensure directory exists
                    $dir = dirname($logFile);
                    if (!is_dir($dir)) {
                        mkdir($dir, 0777, true);
                    }

                    if (file_exists($logFile)) {
                        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                        $lines[] = trim($logEntry);
                        if (count($lines) > 50) {
                            $lines = array_slice($lines, -50);
                        }
                        file_put_contents($logFile, implode(PHP_EOL, $lines) . PHP_EOL);
                    } else {
                        file_put_contents($logFile, trim($logEntry) . PHP_EOL);
                    }
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
