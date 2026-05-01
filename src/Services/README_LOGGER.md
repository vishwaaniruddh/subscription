# Logger Service Documentation

## Overview

The `Logger` service provides centralized logging functionality for the Subscription Management Module. It logs errors, validation failures, and transactions to a file with timestamps and full context.

## Features

- **File-based logging**: Logs are written to `logs/application.log` by default
- **ISO 8601 timestamps**: All log entries include standardized timestamps
- **JSON format**: Structured logging for easy parsing and analysis
- **Thread-safe writes**: Uses file locking to prevent race conditions
- **Automatic directory creation**: Creates log directory if it doesn't exist

## Usage

### Basic Initialization

```php
use App\Services\Logger;

// Use default log directory (logs/)
$logger = new Logger();

// Use custom log directory
$logger = new Logger('/path/to/custom/logs');
```

### Logging Errors

Log database errors, exceptions, and other error conditions:

```php
$logger->logError(
    'Database connection failed',
    [
        'exception' => 'PDOException',
        'message' => 'SQLSTATE[HY000] [2002] Connection refused',
        'host' => 'localhost',
        'database' => 'subscription_db'
    ]
);
```

### Logging Validation Failures

Log validation failures with error codes and request details (Requirement 13.5):

```php
$logger->logValidationFailure(
    'USER_LIMIT_EXCEEDED',
    'The user limit for this service has been reached',
    [
        'service_id' => 123,
        'user_limit' => 10,
        'active_user_count' => 10,
        'user_identifier' => 'user@example.com'
    ]
);
```

### Logging Transactions

Log business transactions like renewals, extensions, and user registrations:

```php
$logger->logTransaction(
    'user_added',
    [
        'service_id' => 456,
        'user_id' => 789,
        'user_identifier' => 'newuser@example.com'
    ]
);

$logger->logTransaction(
    'subscription_renewed',
    [
        'service_id' => 123,
        'old_end_date' => '2024-12-31',
        'new_end_date' => '2025-12-31'
    ]
);
```

## Log Entry Format

All log entries are written as JSON objects with the following structure:

```json
{
  "timestamp": "2024-01-15T10:30:45+00:00",
  "level": "ERROR|VALIDATION_FAILURE|TRANSACTION",
  "message": "Human-readable message",
  "context": {
    "key": "value"
  }
}
```

### Error Log Entry

```json
{
  "timestamp": "2024-01-15T10:30:45+00:00",
  "level": "ERROR",
  "message": "Database error in transaction",
  "context": {
    "exception": "PDOException",
    "message": "SQLSTATE[23000]: Integrity constraint violation",
    "code": 23000,
    "sql_state": "23000",
    "driver_code": 1062
  }
}
```

### Validation Failure Log Entry

```json
{
  "timestamp": "2024-01-15T10:30:45+00:00",
  "level": "VALIDATION_FAILURE",
  "error_code": "USER_LIMIT_EXCEEDED",
  "message": "The user limit for this service has been reached",
  "request_details": {
    "service_id": 123,
    "user_limit": 10,
    "active_user_count": 10
  }
}
```

### Transaction Log Entry

```json
{
  "timestamp": "2024-01-15T10:30:45+00:00",
  "level": "TRANSACTION",
  "transaction_type": "user_added",
  "details": {
    "service_id": 456,
    "user_id": 789,
    "user_identifier": "user@example.com"
  }
}
```

## Integration with Existing Services

The Logger is automatically integrated into:

### ValidationService

Logs all validation failures with error codes and context:

```php
$validationService = new ValidationService($serviceRepository, $logger);
$result = $validationService->validateUserCreation($serviceId);
// Automatically logs if validation fails
```

### BaseRepository

Logs all database errors during transactions:

```php
$repository = new ServiceRepository($db, $logger);
$repository->transactional(function() {
    // Database operations
    // Errors are automatically logged
});
```

## Utility Methods

### Get Log File Path

```php
$logFilePath = $logger->getLogFile();
// Returns: /path/to/logs/application.log
```

### Clear Log File

Useful for testing or log rotation:

```php
$logger->clearLog();
```

## Testing

The Logger service includes comprehensive unit and integration tests:

- **Unit Tests**: `tests/Unit/LoggerTest.php`
- **Integration Tests**: `tests/Integration/LoggerIntegrationTest.php`

Run tests:

```bash
./vendor/bin/pest tests/Unit/LoggerTest.php
./vendor/bin/pest tests/Integration/LoggerIntegrationTest.php
```

## Requirements Satisfied

- **Requirement 13.5**: Log all validation failures with timestamp and request details
- Logs all database errors with full context
- Provides structured logging for debugging and monitoring

## Best Practices

1. **Include Context**: Always provide relevant context in log entries
2. **Use Appropriate Methods**: Use `logError()` for errors, `logValidationFailure()` for validation failures, `logTransaction()` for business transactions
3. **Avoid Sensitive Data**: Don't log passwords, API keys, or other sensitive information
4. **Log Rotation**: Implement log rotation to prevent log files from growing too large
5. **Monitoring**: Set up monitoring to alert on error patterns or validation failure spikes
