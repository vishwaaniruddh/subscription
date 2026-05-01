<?php

namespace App\Repositories;

use PDO;
use PDOException;
use Exception;
use App\Services\Logger;

abstract class BaseRepository
{
    protected PDO $db;
    protected int $maxRetries = 3;
    protected ?Logger $logger = null;

    public function __construct(PDO $db, ?Logger $logger = null)
    {
        $this->db = $db;
        $this->logger = $logger ?? new Logger();
    }

    public function beginTransaction(): bool
    {
        return $this->db->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->db->commit();
    }

    public function rollback(): bool
    {
        return $this->db->rollback();
    }

    /**
     * Execute a callback within a transaction with retry logic for deadlocks.
     *
     * @param callable $callback
     * @return mixed
     * @throws Exception
     */
    public function transactional(callable $callback): mixed
    {
        $attempts = 0;
        while ($attempts < $this->maxRetries) {
            try {
                $this->beginTransaction();
                $result = $callback($this);
                $this->commit();
                return $result;
            } catch (PDOException $e) {
                $this->rollback();
                
                // Log database error
                $this->logger->logError(
                    'Database error in transaction',
                    [
                        'exception' => get_class($e),
                        'message' => $e->getMessage(),
                        'code' => $e->getCode(),
                        'sql_state' => $e->errorInfo[0] ?? null,
                        'driver_code' => $e->errorInfo[1] ?? null,
                        'attempt' => $attempts + 1,
                        'max_retries' => $this->maxRetries
                    ]
                );
                
                if ($this->isDeadlock($e)) {
                    $attempts++;
                    usleep(pow(2, $attempts) * 100000); // Exponential backoff
                    continue;
                }
                throw $e;
            } catch (Exception $e) {
                $this->rollback();
                
                // Log general error
                $this->logger->logError(
                    'Error in transaction',
                    [
                        'exception' => get_class($e),
                        'message' => $e->getMessage(),
                        'code' => $e->getCode()
                    ]
                );
                
                throw $e;
            }
        }
        
        // Log transaction failure after max retries
        $this->logger->logError(
            "Transaction failed after {$this->maxRetries} attempts due to deadlocks",
            [
                'max_retries' => $this->maxRetries,
                'attempts' => $attempts
            ]
        );
        
        throw new Exception("Transaction failed after {$this->maxRetries} attempts due to deadlocks.");
    }

    protected function isDeadlock(PDOException $e): bool
    {
        $errorInfo = $e->errorInfo;
        return isset($errorInfo[1]) && ($errorInfo[1] == 1213 || $errorInfo[1] == 1205);
    }
}
