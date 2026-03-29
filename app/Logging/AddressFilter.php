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
     * @param  \Illuminate\Log\Logger  $logger
     * @return void
     */
    public function __invoke(Logger $logger)
    {
        // Allowed addresses (Hardhat accounts #1 to #5)
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
        ];

        // Format to lowercase for case-insensitive comparison
        $allowedAddresses = array_map('strtolower', $allowedAddresses);

        $monolog = $logger->getLogger();
        $handlers = $monolog->getHandlers();
        $newHandlers = [];

        foreach ($handlers as $handler) {
            $newHandlers[] = new class($handler, $allowedAddresses) extends HandlerWrapper {
                private $allowedAddresses;

                public function __construct(HandlerInterface $handler, array $allowedAddresses)
                {
                    parent::__construct($handler);
                    $this->allowedAddresses = $allowedAddresses;
                }

                public function isHandling(LogRecord $record): bool
                {
                    if (!parent::isHandling($record)) {
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
                    if (!empty($record->context)) {
                        $contextJson = strtolower(json_encode($record->context));
                        if (strpos($contextJson, '0x') !== false) {
                            foreach ($this->allowedAddresses as $address) {
                                if (strpos($contextJson, $address) !== false) {
                                    return true;
                                }
                            }
                        }
                    }

                    return false; // Has 0x address but not in our allowed list -> ignore
                }

                public function handle(LogRecord $record): bool
                {
                    if (!$this->isHandling($record)) {
                        return false;
                    }

                    return $this->handler->handle($record);
                }
            };
        }

        $monolog->setHandlers($newHandlers);
    }
}
