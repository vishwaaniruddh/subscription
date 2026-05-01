<?php

namespace App\Services;

use DateTime;
use Exception;

/**
 * Logger service for logging errors, validation failures, and transactions
 * Implements file-based logging with timestamps and context
 * 
 * Requirements: 13.5
 */
class Logger
{
    private string $logDirectory;
    private string $logFile;

    public function __construct(?string $logDirectory = null)
    {
        $this->logDirectory = $logDirectory ?? __DIR__ . '/../../logs';
        $this->logFile = $this->logDirectory . '/application.log';
        
        // Create logs directory if it doesn't exist
        if (!is_dir($this->logDirectory)) {
            mkdir($this->logDirectory, 0755, true);
        }
    }

    /**
     * Log an error with full context
     * 
     * @param string $message Error message
     * @param array $context Additional context (exception, stack trace, etc.)
     * @return bool Success status
     */
    public function logError(string $message, array $context = []): bool
    {
        $logEntry = [
            'timestamp' => $this->getTimestamp(),
            'level' => 'ERROR',
            'message' => $message,
            'context' => $context
        ];

        return $this->writeLog($logEntry);
    }

    /**
     * Log a validation failure with timestamp and request details
     * 
     * @param string $errorCode Error code (e.g., USER_LIMIT_EXCEEDED, SUBSCRIPTION_EXPIRED)
     * @param string $message Human-readable error message
     * @param array $requestDetails Request details (service_id, user_identifier, etc.)
     * @return bool Success status
     */
    public function logValidationFailure(string $errorCode, string $message, array $requestDetails = []): bool
    {
        $logEntry = [
            'timestamp' => $this->getTimestamp(),
            'level' => 'VALIDATION_FAILURE',
            'error_code' => $errorCode,
            'message' => $message,
            'request_details' => $requestDetails
        ];

        return $this->writeLog($logEntry);
    }

    /**
     * Log a transaction (renewal, extension, user registration, etc.)
     * 
     * @param string $transactionType Type of transaction (renewal, extension, user_added, etc.)
     * @param array $details Transaction details
     * @return bool Success status
     */
    public function logTransaction(string $transactionType, array $details = []): bool
    {
        $logEntry = [
            'timestamp' => $this->getTimestamp(),
            'level' => 'TRANSACTION',
            'transaction_type' => $transactionType,
            'details' => $details
        ];

        return $this->writeLog($logEntry);
    }

    /**
     * Write log entry to file
     * 
     * @param array $logEntry Log entry data
     * @return bool Success status
     */
    private function writeLog(array $logEntry): bool
    {
        try {
            $logLine = json_encode($logEntry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
            
            // Use file_put_contents with FILE_APPEND and LOCK_EX for thread-safe writes
            $result = file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);
            
            return $result !== false;
        } catch (Exception $e) {
            // If logging fails, write to error_log as fallback
            error_log("Logger failed to write log: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get current timestamp in ISO 8601 format
     * 
     * @return string ISO 8601 formatted timestamp
     */
    private function getTimestamp(): string
    {
        return (new DateTime())->format('c'); // ISO 8601 format
    }

    /**
     * Get the log file path
     * 
     * @return string Log file path
     */
    public function getLogFile(): string
    {
        return $this->logFile;
    }

    /**
     * Clear the log file (useful for testing)
     * 
     * @return bool Success status
     */
    public function clearLog(): bool
    {
        if (file_exists($this->logFile)) {
            return unlink($this->logFile);
        }
        return true;
    }
}
