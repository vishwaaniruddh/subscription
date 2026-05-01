<?php

use App\Services\Logger;

beforeEach(function () {
    // Use a temporary directory for test logs
    $this->testLogDir = sys_get_temp_dir() . '/subscription_test_logs_' . uniqid();
    $this->logger = new Logger($this->testLogDir);
});

afterEach(function () {
    // Clean up test logs
    if ($this->logger) {
        $this->logger->clearLog();
    }
    if (is_dir($this->testLogDir)) {
        rmdir($this->testLogDir);
    }
});

test('logger creates log directory if it does not exist', function () {
    expect(is_dir($this->testLogDir))->toBeTrue();
});

test('logError writes error to log file', function () {
    $result = $this->logger->logError('Test error message', ['key' => 'value']);
    
    expect($result)->toBeTrue();
    expect(file_exists($this->logger->getLogFile()))->toBeTrue();
    
    $logContent = file_get_contents($this->logger->getLogFile());
    $logEntry = json_decode($logContent, true);
    
    expect($logEntry)->toHaveKey('timestamp');
    expect($logEntry['level'])->toBe('ERROR');
    expect($logEntry['message'])->toBe('Test error message');
    expect($logEntry['context'])->toBe(['key' => 'value']);
});

test('logValidationFailure writes validation failure to log file', function () {
    $result = $this->logger->logValidationFailure(
        'USER_LIMIT_EXCEEDED',
        'User limit has been reached',
        ['service_id' => 123, 'user_identifier' => 'user@example.com']
    );
    
    expect($result)->toBeTrue();
    
    $logContent = file_get_contents($this->logger->getLogFile());
    $logEntry = json_decode($logContent, true);
    
    expect($logEntry)->toHaveKey('timestamp');
    expect($logEntry['level'])->toBe('VALIDATION_FAILURE');
    expect($logEntry['error_code'])->toBe('USER_LIMIT_EXCEEDED');
    expect($logEntry['message'])->toBe('User limit has been reached');
    expect($logEntry['request_details'])->toBe([
        'service_id' => 123,
        'user_identifier' => 'user@example.com'
    ]);
});

test('logTransaction writes transaction to log file', function () {
    $result = $this->logger->logTransaction(
        'user_added',
        ['service_id' => 456, 'user_id' => 789]
    );
    
    expect($result)->toBeTrue();
    
    $logContent = file_get_contents($this->logger->getLogFile());
    $logEntry = json_decode($logContent, true);
    
    expect($logEntry)->toHaveKey('timestamp');
    expect($logEntry['level'])->toBe('TRANSACTION');
    expect($logEntry['transaction_type'])->toBe('user_added');
    expect($logEntry['details'])->toBe([
        'service_id' => 456,
        'user_id' => 789
    ]);
});

test('logger appends multiple log entries', function () {
    $this->logger->logError('First error');
    $this->logger->logError('Second error');
    $this->logger->logValidationFailure('TEST_CODE', 'Test validation');
    
    $logContent = file_get_contents($this->logger->getLogFile());
    $lines = explode(PHP_EOL, trim($logContent));
    
    expect(count($lines))->toBe(3);
    
    $firstEntry = json_decode($lines[0], true);
    $secondEntry = json_decode($lines[1], true);
    $thirdEntry = json_decode($lines[2], true);
    
    expect($firstEntry['message'])->toBe('First error');
    expect($secondEntry['message'])->toBe('Second error');
    expect($thirdEntry['error_code'])->toBe('TEST_CODE');
});

test('timestamp is in ISO 8601 format', function () {
    $this->logger->logError('Test error');
    
    $logContent = file_get_contents($this->logger->getLogFile());
    $logEntry = json_decode($logContent, true);
    
    // ISO 8601 format: 2024-01-15T10:30:45+00:00
    expect($logEntry['timestamp'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/');
});

test('clearLog removes log file', function () {
    $this->logger->logError('Test error');
    expect(file_exists($this->logger->getLogFile()))->toBeTrue();
    
    $result = $this->logger->clearLog();
    expect($result)->toBeTrue();
    expect(file_exists($this->logger->getLogFile()))->toBeFalse();
});

test('clearLog returns true when log file does not exist', function () {
    $result = $this->logger->clearLog();
    expect($result)->toBeTrue();
});

test('logError with database exception context', function () {
    $exceptionContext = [
        'exception' => 'PDOException',
        'message' => 'SQLSTATE[23000]: Integrity constraint violation',
        'query' => 'INSERT INTO users ...',
        'service_id' => 123
    ];
    
    $result = $this->logger->logError('Database error occurred', $exceptionContext);
    
    expect($result)->toBeTrue();
    
    $logContent = file_get_contents($this->logger->getLogFile());
    $logEntry = json_decode($logContent, true);
    
    expect($logEntry['context']['exception'])->toBe('PDOException');
    expect($logEntry['context']['query'])->toBe('INSERT INTO users ...');
});
