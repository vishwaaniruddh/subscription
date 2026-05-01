# Design Document: Subscription Management Module

## Overview

The Subscription Management Module is a web-based system that manages client subscriptions with hierarchical organization (Client → Project → Service) and enforces user limits per service. The system provides REST API endpoints for all operations and a vanilla JavaScript frontend for administrative tasks.

### Key Design Goals

1. **Hierarchical Organization**: Three-tier structure (Client → Project → Service) for flexible subscription management
2. **User Limit Enforcement**: Real-time validation preventing user creation beyond configured limits
3. **Subscription Lifecycle**: Track active/expired subscriptions with renewal and extension capabilities
4. **Data Integrity**: Transaction-based operations with row-level locking to prevent race conditions
5. **API-First Architecture**: All operations accessible via RESTful endpoints with JSON payloads
6. **Responsive UI**: Dynamic frontend without full page reloads using vanilla JavaScript

### Technology Stack

- **Backend**: PHP 8.x with PDO for database access
- **Database**: MySQL 8.x with InnoDB engine for transaction support
- **Frontend**: Vanilla JavaScript (ES6+) with Fetch API
- **Styling**: Tailwind CSS for responsive design
- **API**: RESTful architecture with JSON request/response format

## Architecture

### System Architecture

The system follows a three-tier architecture:

```
┌─────────────────────────────────────────┐
│         Frontend Layer                  │
│  (Vanilla JS + Tailwind CSS)           │
│  - Client Management UI                 │
│  - Project Management UI                │
│  - Service Management UI                │
│  - Reporting Dashboard                  │
└──────────────┬──────────────────────────┘
               │ HTTP/JSON
               │
┌──────────────▼──────────────────────────┐
│         API Gateway Layer               │
│  (PHP REST Controllers)                 │
│  - Client Controller                    │
│  - Project Controller                   │
│  - Service Controller                   │
│  - Validation Controller                │
│  - Reporting Controller                 │
└──────────────┬──────────────────────────┘
               │
┌──────────────▼──────────────────────────┐
│       Business Logic Layer              │
│  - Client Service                       │
│  - Project Service                      │
│  - Service Manager                      │
│  - Validation Service                   │
│  - Subscription Lifecycle Manager       │
└──────────────┬──────────────────────────┘
               │
┌──────────────▼──────────────────────────┐
│       Data Access Layer                 │
│  (Repository Pattern)                   │
│  - Client Repository                    │
│  - Project Repository                   │
│  - Service Repository                   │
│  - User Repository                      │
│  - History Repository                   │
└──────────────┬──────────────────────────┘
               │
┌──────────────▼──────────────────────────┐
│         MySQL Database                  │
│  - clients                              │
│  - projects                             │
│  - services                             │
│  - users                                │
│  - subscription_history                 │
└─────────────────────────────────────────┘
```

### Component Responsibilities

#### API Gateway Layer
- Route HTTP requests to appropriate controllers
- Validate request format and required parameters
- Handle CORS headers for cross-origin requests
- Return standardized JSON responses with appropriate HTTP status codes
- Catch and format exceptions into error responses

#### Business Logic Layer
- **Client Service**: CRUD operations for client entities
- **Project Service**: CRUD operations for project entities with client association
- **Service Manager**: CRUD operations for service entities with subscription logic
- **Validation Service**: User creation eligibility checks against limits and subscription status
- **Subscription Lifecycle Manager**: Renewal, extension, and expiration logic

#### Data Access Layer
- Abstract database operations using repository pattern
- Execute queries with prepared statements to prevent SQL injection
- Manage database transactions for atomic operations
- Implement row-level locking for concurrent access control

### Concurrency Control Strategy

To prevent race conditions during user creation (Requirement 15):

1. **Transaction Isolation**: Use `READ COMMITTED` isolation level
2. **Row-Level Locking**: Use `SELECT ... FOR UPDATE` when reading active_user_count
3. **Atomic Operations**: Wrap validation + increment in single transaction
4. **Retry Logic**: Implement exponential backoff for deadlock retries (max 3 attempts)

```php
// Pseudocode for user creation with locking
BEGIN TRANSACTION
  SELECT active_user_count, user_limit 
  FROM services 
  WHERE id = ? 
  FOR UPDATE;  // Row-level lock
  
  IF active_user_count < user_limit THEN
    INSERT INTO users (service_id, user_identifier, status);
    UPDATE services SET active_user_count = active_user_count + 1;
    COMMIT;
  ELSE
    ROLLBACK;
    RETURN error;
  END IF
END TRANSACTION
```

## Components and Interfaces

### REST API Endpoints

#### Client Management (Requirement 1)

```
POST   /api/clients
GET    /api/clients
GET    /api/clients/{id}
PUT    /api/clients/{id}
DELETE /api/clients/{id}
```

**POST /api/clients**
- Request: `{ "name": "string", "contact_info": "string" }`
- Response: `{ "id": "integer", "name": "string", "contact_info": "string", "created_at": "ISO8601" }`
- Status: 201 Created

**GET /api/clients/{id}**
- Response: `{ "id": "integer", "name": "string", "contact_info": "string", "created_at": "ISO8601", "updated_at": "ISO8601" }`
- Status: 200 OK, 404 Not Found

#### Project Management (Requirement 2)

```
POST   /api/projects
GET    /api/clients/{clientId}/projects
GET    /api/projects/{id}
PUT    /api/projects/{id}
DELETE /api/projects/{id}
```

**POST /api/projects**
- Request: `{ "client_id": "integer", "name": "string", "description": "string" }`
- Response: `{ "id": "integer", "client_id": "integer", "name": "string", "description": "string", "created_at": "ISO8601" }`
- Status: 201 Created

#### Service Management (Requirement 3, 4)

```
POST   /api/services
GET    /api/projects/{projectId}/services
GET    /api/services/{id}
PUT    /api/services/{id}
DELETE /api/services/{id}
PUT    /api/services/{id}/user-limit
```

**POST /api/services**
- Request: `{ "project_id": "integer", "service_type": "string", "user_limit": "integer", "start_date": "ISO8601", "end_date": "ISO8601" }`
- Response: `{ "id": "integer", "project_id": "integer", "service_type": "string", "user_limit": "integer", "active_user_count": 0, "start_date": "ISO8601", "end_date": "ISO8601", "created_at": "ISO8601" }`
- Status: 201 Created

**GET /api/services/{id}**
- Response: `{ "id": "integer", "project_id": "integer", "service_type": "string", "user_limit": "integer", "active_user_count": "integer", "start_date": "ISO8601", "end_date": "ISO8601", "status": "active|expired" }`
- Status: 200 OK, 404 Not Found

#### User Validation and Management (Requirement 5, 6)

```
POST   /api/services/{serviceId}/validate-user
POST   /api/services/{serviceId}/users
DELETE /api/services/{serviceId}/users/{userId}
GET    /api/services/{serviceId}/users
```

**POST /api/services/{serviceId}/validate-user**
- Request: `{ "user_identifier": "string" }`
- Response (Success): `{ "valid": true, "service_id": "integer", "current_count": "integer", "user_limit": "integer" }`
- Response (Failure): `{ "valid": false, "error_code": "USER_LIMIT_EXCEEDED|SUBSCRIPTION_EXPIRED", "message": "string", "current_count": "integer", "user_limit": "integer", "expiry_date": "ISO8601" }`
- Status: 200 OK
- Performance: Must complete within 500ms

**POST /api/services/{serviceId}/users**
- Request: `{ "user_identifier": "string" }`
- Response: `{ "id": "integer", "service_id": "integer", "user_identifier": "string", "status": "active", "created_at": "ISO8601" }`
- Status: 201 Created, 400 Bad Request (limit exceeded)

**DELETE /api/services/{serviceId}/users/{userId}**
- Response: `{ "success": true, "message": "User deactivated" }`
- Status: 200 OK, 404 Not Found

#### Subscription Lifecycle (Requirement 7, 8, 9)

```
POST   /api/services/{serviceId}/renew
POST   /api/services/{serviceId}/extend
GET    /api/services/{serviceId}/status
```

**POST /api/services/{serviceId}/renew**
- Request: `{ "new_end_date": "ISO8601" }`
- Response: `{ "id": "integer", "end_date": "ISO8601", "renewed_at": "ISO8601" }`
- Status: 200 OK

**POST /api/services/{serviceId}/extend**
- Request: `{ "new_user_limit": "integer", "new_end_date": "ISO8601" }` (new_end_date optional)
- Response: `{ "id": "integer", "user_limit": "integer", "end_date": "ISO8601", "extended_at": "ISO8601" }`
- Status: 200 OK, 400 Bad Request (new limit < active users)

#### Reporting (Requirement 14)

```
GET    /api/clients/{clientId}/utilization
GET    /api/projects/{projectId}/utilization
GET    /api/services/{serviceId}/utilization
GET    /api/services/expiring?days={days}
GET    /api/services/high-utilization?threshold={percentage}
```

**GET /api/services/{serviceId}/utilization**
- Response: `{ "service_id": "integer", "user_limit": "integer", "active_user_count": "integer", "utilization_percentage": "float", "status": "active|expired" }`
- Status: 200 OK

**GET /api/services/expiring?days={days}**
- Response: `{ "services": [{ "id": "integer", "project_id": "integer", "service_type": "string", "end_date": "ISO8601", "days_until_expiry": "integer" }] }`
- Status: 200 OK

### Frontend Components

#### Client Management Interface
- **ClientList Component**: Display paginated list of clients
- **ClientForm Component**: Create/edit client with name and contact info
- **ClientDetail Component**: View client details with associated projects

#### Project Management Interface
- **ProjectList Component**: Display projects for selected client
- **ProjectForm Component**: Create/edit project with client association
- **ProjectDetail Component**: View project details with associated services

#### Service Management Interface
- **ServiceList Component**: Display services for selected project
- **ServiceForm Component**: Create/edit service with type, limits, and dates
- **ServiceDetail Component**: View service details with user count and status
- **UserLimitBadge Component**: Visual indicator of utilization (green < 70%, yellow 70-90%, red > 90%)
- **SubscriptionStatus Component**: Display active/expired status with expiry date

#### Subscription Operations Interface
- **RenewSubscription Component**: Form to extend subscription end date
- **ExtendSubscription Component**: Form to increase user limit and optionally extend date
- **ValidationTest Component**: Test user creation validation for a service

#### Reporting Dashboard
- **UtilizationChart Component**: Visual representation of service utilization
- **ExpiringSubscriptions Component**: List of subscriptions expiring soon
- **HighUtilization Component**: List of services near capacity

### Error Response Format

All error responses follow this structure (Requirement 13):

```json
{
  "error": {
    "code": "ERROR_CODE",
    "message": "Human-readable error message",
    "context": {
      "field": "additional context"
    }
  }
}
```

**Error Codes**:
- `USER_LIMIT_EXCEEDED`: Active user count equals or exceeds limit
- `SUBSCRIPTION_EXPIRED`: Current date is after subscription end date
- `INVALID_USER_LIMIT`: New user limit is less than active user count
- `INVALID_DATE_RANGE`: Start date is after end date
- `RESOURCE_NOT_FOUND`: Requested entity does not exist
- `VALIDATION_ERROR`: Input validation failed
- `DATABASE_ERROR`: Database operation failed

## Data Models

### Database Schema (Requirement 11)

#### clients Table

```sql
CREATE TABLE clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    contact_info TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name)
) ENGINE=InnoDB;
```

#### projects Table

```sql
CREATE TABLE projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    INDEX idx_client_id (client_id),
    INDEX idx_name (name)
) ENGINE=InnoDB;
```

#### services Table

```sql
CREATE TABLE services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    service_type VARCHAR(50) NOT NULL,
    user_limit INT NOT NULL CHECK (user_limit > 0),
    active_user_count INT NOT NULL DEFAULT 0,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_project_id (project_id),
    INDEX idx_service_type (service_type),
    INDEX idx_end_date (end_date),
    CHECK (end_date >= start_date),
    CHECK (active_user_count >= 0),
    CHECK (active_user_count <= user_limit)
) ENGINE=InnoDB;
```

#### users Table

```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT NOT NULL,
    user_identifier VARCHAR(255) NOT NULL,
    status ENUM('active', 'deactivated') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deactivated_at TIMESTAMP NULL,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    INDEX idx_service_id (service_id),
    INDEX idx_user_identifier (user_identifier),
    INDEX idx_status (status),
    UNIQUE KEY unique_active_user (service_id, user_identifier, status)
) ENGINE=InnoDB;
```

#### subscription_history Table

```sql
CREATE TABLE subscription_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT NOT NULL,
    action_type ENUM('renewal', 'extension', 'user_limit_change', 'user_added', 'user_deactivated') NOT NULL,
    old_value VARCHAR(255),
    new_value VARCHAR(255),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    INDEX idx_service_id (service_id),
    INDEX idx_action_type (action_type),
    INDEX idx_timestamp (timestamp)
) ENGINE=InnoDB;
```

### Entity Relationships

```
clients (1) ──────< (N) projects
                         │
                         │
                         │
                    (1) ─┴─────< (N) services
                                      │
                                      │
                                      ├────< (N) users
                                      │
                                      └────< (N) subscription_history
```

### Domain Models

#### Client Entity
```php
class Client {
    private int $id;
    private string $name;
    private string $contactInfo;
    private DateTime $createdAt;
    private DateTime $updatedAt;
}
```

#### Project Entity
```php
class Project {
    private int $id;
    private int $clientId;
    private string $name;
    private string $description;
    private DateTime $createdAt;
    private DateTime $updatedAt;
}
```

#### Service Entity
```php
class Service {
    private int $id;
    private int $projectId;
    private string $serviceType;
    private int $userLimit;
    private int $activeUserCount;
    private DateTime $startDate;
    private DateTime $endDate;
    private DateTime $createdAt;
    private DateTime $updatedAt;
    
    public function isActive(): bool {
        $now = new DateTime();
        return $now >= $this->startDate && $now <= $this->endDate;
    }
    
    public function canAddUser(): bool {
        return $this->isActive() && $this->activeUserCount < $this->userLimit;
    }
    
    public function getUtilizationPercentage(): float {
        return ($this->activeUserCount / $this->userLimit) * 100;
    }
}
```

#### User Entity
```php
class User {
    private int $id;
    private int $serviceId;
    private string $userIdentifier;
    private string $status; // 'active' or 'deactivated'
    private DateTime $createdAt;
    private ?DateTime $deactivatedAt;
}
```

#### SubscriptionHistory Entity
```php
class SubscriptionHistory {
    private int $id;
    private int $serviceId;
    private string $actionType;
    private ?string $oldValue;
    private ?string $newValue;
    private DateTime $timestamp;
}
```

### Data Validation Rules

1. **Client**:
   - name: Required, max 255 characters
   - contact_info: Optional, text

2. **Project**:
   - client_id: Required, must reference existing client
   - name: Required, max 255 characters
   - description: Optional, text

3. **Service**:
   - project_id: Required, must reference existing project
   - service_type: Required, one of ['web', 'mobile', 'other']
   - user_limit: Required, positive integer > 0
   - start_date: Required, ISO 8601 date format
   - end_date: Required, ISO 8601 date format, must be >= start_date

4. **User**:
   - service_id: Required, must reference existing service
   - user_identifier: Required, max 255 characters
   - Must not duplicate active user for same service

5. **Subscription Operations**:
   - Renewal: new_end_date must be >= current end_date
   - Extension: new_user_limit must be >= active_user_count


## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property Reflection

After analyzing all acceptance criteria, I identified several areas of redundancy:

1. **CRUD Endpoint Examples**: Requirements 1.1-1.4, 2.1-2.4, 3.1-3.4 all specify endpoint existence. These will be covered by integration examples rather than individual properties.

2. **Unique ID Assignment**: Requirements 1.5, 2.5, 3.5 all specify unique ID assignment. These can be combined into a single property about entity creation.

3. **Round-Trip Persistence**: Requirements 1.6, 2.7, 3.8 all specify database persistence. These can be combined into a single property about data persistence.

4. **Validation Redundancy**: Requirements 5.3 and 5.4 are complementary (success vs failure cases). These can be combined into a single property about validation correctness.

5. **Extension Validation**: Requirements 9.3 and 9.4 state the same constraint from different angles. These will be combined.

6. **Count Tracking**: Requirements 4.4, 5.7, and 6.2 all relate to maintaining accurate user counts. These can be combined into properties about count invariants.

### Property 1: Entity Creation Assigns Unique Identifiers

*For any* valid client, project, or service data, creating the entity should result in a unique identifier being assigned that differs from all previously created entities of that type.

**Validates: Requirements 1.5, 2.5, 3.5**

### Property 2: Entity Persistence Round-Trip

*For any* client, project, or service entity, creating it and then retrieving it by ID should return an equivalent entity with all fields matching the original values.

**Validates: Requirements 1.6, 2.7, 3.8**

### Property 3: Client-Project Relationship

*For any* client, creating multiple projects associated with that client should result in all projects being retrievable via the client's project list.

**Validates: Requirements 2.6**

### Property 4: Project-Service Relationship

*For any* project, creating multiple services associated with that project should result in all services being retrievable via the project's service list.

**Validates: Requirements 3.7**

### Property 5: Valid Service Types

*For any* service type in the set {web, mobile, other}, creating a service with that type should succeed, and creating a service with a type outside this set should fail.

**Validates: Requirements 3.6**

### Property 6: User Limit Validation

*For any* service creation or update request, if user_limit is not provided or is <= 0, the request should be rejected; if user_limit > 0, it should be accepted.

**Validates: Requirements 4.1, 4.2**

### Property 7: Active User Count Accuracy

*For any* service, the active_user_count should always equal the number of users with status='active' associated with that service.

**Validates: Requirements 4.4**

### Property 8: User Registration Increments Count

*For any* service with available capacity, successfully registering a user should increase the active_user_count by exactly 1.

**Validates: Requirements 5.7**

### Property 9: User Deactivation Decrements Count

*For any* service with active users, deactivating a user should decrease the active_user_count by exactly 1.

**Validates: Requirements 6.2**

### Property 10: User Limit Enforcement

*For any* service where active_user_count >= user_limit, attempting to register a new user should be rejected with error code USER_LIMIT_EXCEEDED.

**Validates: Requirements 5.8**

### Property 11: Validation Correctness

*For any* service, validation should return success if and only if active_user_count < user_limit AND the subscription is active (current date is between start_date and end_date).

**Validates: Requirements 5.2, 5.3, 5.4, 7.7**

### Property 12: Deactivation Audit Trail

*For any* user deactivation, a record should exist in the users table with status='deactivated' and a non-null deactivated_at timestamp.

**Validates: Requirements 6.3**

### Property 13: Subscription Date Requirements

*For any* service creation request, if start_date or end_date is missing, the request should be rejected.

**Validates: Requirements 7.1, 7.2**

### Property 14: Date Format Consistency

*For any* service entity retrieved from the system, the start_date and end_date fields should be in ISO 8601 format (YYYY-MM-DD).

**Validates: Requirements 7.3**

### Property 15: Active Subscription Status

*For any* service where the current date is >= start_date AND <= end_date, the subscription status should be reported as 'active'.

**Validates: Requirements 7.5**

### Property 16: Expired Subscription Status

*For any* service where the current date > end_date, the subscription status should be reported as 'expired'.

**Validates: Requirements 7.6**

### Property 17: Renewal Requires End Date

*For any* renewal request without a new_end_date parameter, the request should be rejected.

**Validates: Requirements 8.2**

### Property 18: Renewal Updates End Date

*For any* service and valid new_end_date, renewing the subscription should update the end_date field to the new value.

**Validates: Requirements 8.3**

### Property 19: Renewal Preserves User Limit and Count

*For any* service, renewing the subscription should not change the user_limit or active_user_count values.

**Validates: Requirements 8.4, 8.5**

### Property 20: Renewal Creates History Record

*For any* successful renewal, a record should be created in subscription_history with action_type='renewal' and a timestamp.

**Validates: Requirements 8.6**

### Property 21: Extension Limit Validation

*For any* extension request where new_user_limit < active_user_count, the request should be rejected with error code INVALID_USER_LIMIT.

**Validates: Requirements 9.3, 9.4**

### Property 22: Extension Updates User Limit

*For any* service and valid new_user_limit (>= active_user_count), extending the subscription should update the user_limit field to the new value.

**Validates: Requirements 9.5**

### Property 23: Extension Creates History Record

*For any* successful extension, a record should be created in subscription_history with action_type='extension' and a timestamp.

**Validates: Requirements 9.6**

### Property 24: Extension Date Update

*For any* extension request that includes a new_end_date parameter, the service's end_date should be updated to the new value.

**Validates: Requirements 9.7**

### Property 25: JSON Response Format

*For any* API endpoint call, the response should be valid JSON that can be parsed without errors.

**Validates: Requirements 10.3**

### Property 26: HTTP Status Code Correctness

*For any* API operation, the HTTP status code should match the operation result: 200/201 for success, 400 for validation errors, 404 for not found, 500 for server errors.

**Validates: Requirements 10.4**

### Property 27: Error Response Structure

*For any* error response, the JSON should contain an 'error' object with 'code' and 'message' fields.

**Validates: Requirements 10.5, 13.2, 13.3**

### Property 28: Validation Error Status Code

*For any* validation failure (user limit exceeded, subscription expired, invalid input), the HTTP status code should be 400.

**Validates: Requirements 13.1**

### Property 29: Error Context Inclusion

*For any* USER_LIMIT_EXCEEDED error, the response should include context with current_count and user_limit; for SUBSCRIPTION_EXPIRED errors, it should include expiry_date.

**Validates: Requirements 13.4**

### Property 30: Input Validation

*For any* API request with invalid input data (missing required fields, invalid types, constraint violations), the request should be rejected before processing.

**Validates: Requirements 10.7**

### Property 31: Foreign Key Integrity

*For any* attempt to create a project with non-existent client_id, or service with non-existent project_id, or user with non-existent service_id, the operation should be rejected.

**Validates: Requirements 11.7**

### Property 32: Utilization Calculation

*For any* service, the utilization percentage should equal (active_user_count / user_limit) × 100.

**Validates: Requirements 14.4**

### Property 33: Expiring Services Filter

*For any* query for services expiring within N days, all returned services should have end_date within N days from the current date, and no services meeting this criteria should be omitted.

**Validates: Requirements 14.5**

### Property 34: High Utilization Filter

*For any* query for services with utilization above threshold T, all returned services should have utilization_percentage >= T, and no services meeting this criteria should be omitted.

**Validates: Requirements 14.6**

### Property 35: Concurrent User Creation Safety

*For any* service at exactly (user_limit - 1) active users, if N concurrent user creation requests arrive (where N > 1), at most 1 should succeed and the rest should fail with USER_LIMIT_EXCEEDED.

**Validates: Requirements 15.1**

### Property 36: Transaction Atomicity

*For any* failed user registration operation, the active_user_count should remain unchanged and no user record should be created.

**Validates: Requirements 15.4**

### Property 37: Transaction Retry

*For any* user registration that fails due to transient database errors, the system should retry up to 3 times before returning an error to the caller.

**Validates: Requirements 15.5**

### Property 38: Form Submission API Integration

*For any* form submission in the frontend, an HTTP request should be made to the corresponding API endpoint.

**Validates: Requirements 12.7**

### Property 39: API Response Feedback

*For any* API response (success or error), a message should be displayed in the user interface.

**Validates: Requirements 12.8**

### Property 40: Dynamic UI Updates

*For any* successful API operation, the interface should update to reflect the changes without triggering a full page reload.

**Validates: Requirements 12.9**

### Property 41: Validation Logging

*For any* validation failure, an entry should be created in the system logs containing the timestamp, error type, and request details.

**Validates: Requirements 13.5**

## Error Handling

### Error Categories

1. **Validation Errors (HTTP 400)**
   - Missing required fields
   - Invalid data types or formats
   - Constraint violations (user_limit <= 0, end_date < start_date)
   - Business rule violations (user limit exceeded, subscription expired)

2. **Not Found Errors (HTTP 404)**
   - Requested entity does not exist
   - Invalid entity ID

3. **Conflict Errors (HTTP 409)**
   - Duplicate entity creation
   - Foreign key constraint violations

4. **Server Errors (HTTP 500)**
   - Database connection failures
   - Unexpected exceptions
   - Transaction deadlocks after retry exhaustion

### Error Response Format

All errors follow this structure:

```json
{
  "error": {
    "code": "ERROR_CODE",
    "message": "Human-readable description",
    "context": {
      "field1": "value1",
      "field2": "value2"
    }
  }
}
```

### Specific Error Codes

| Error Code | HTTP Status | Description | Context Fields |
|------------|-------------|-------------|----------------|
| USER_LIMIT_EXCEEDED | 400 | Active user count at or above limit | current_count, user_limit |
| SUBSCRIPTION_EXPIRED | 400 | Current date after subscription end date | expiry_date, current_date |
| INVALID_USER_LIMIT | 400 | New user limit below active user count | active_user_count, requested_limit |
| INVALID_DATE_RANGE | 400 | Start date after end date | start_date, end_date |
| MISSING_REQUIRED_FIELD | 400 | Required field not provided | field_name |
| INVALID_FIELD_TYPE | 400 | Field has wrong data type | field_name, expected_type, actual_type |
| RESOURCE_NOT_FOUND | 404 | Entity does not exist | resource_type, resource_id |
| FOREIGN_KEY_VIOLATION | 409 | Referenced entity does not exist | field_name, referenced_id |
| DATABASE_ERROR | 500 | Database operation failed | error_message |
| TRANSACTION_FAILED | 500 | Transaction could not complete after retries | retry_count |

### Error Handling Strategy

1. **Input Validation**: Validate all inputs at API gateway before passing to business logic
2. **Database Errors**: Catch PDO exceptions and convert to appropriate error responses
3. **Transaction Failures**: Implement retry logic with exponential backoff for deadlocks
4. **Logging**: Log all errors with full context for debugging
5. **User-Friendly Messages**: Provide clear, actionable error messages to API consumers

### Exception Handling Flow

```
API Request
    │
    ├─> Input Validation
    │   └─> ValidationException → HTTP 400
    │
    ├─> Business Logic
    │   ├─> BusinessRuleException → HTTP 400
    │   └─> NotFoundException → HTTP 404
    │
    └─> Database Operations
        ├─> PDOException (Constraint) → HTTP 409
        ├─> PDOException (Deadlock) → Retry → HTTP 500
        └─> PDOException (Other) → HTTP 500
```

## Testing Strategy

### Dual Testing Approach

The system will employ both unit testing and property-based testing to ensure comprehensive coverage:

- **Unit Tests**: Verify specific examples, edge cases, and error conditions
- **Property Tests**: Verify universal properties across all inputs

This dual approach ensures that unit tests catch concrete bugs while property tests verify general correctness across a wide range of inputs.

### Unit Testing

Unit tests will focus on:

1. **Specific Examples**: Concrete scenarios demonstrating correct behavior
   - Creating a client with valid data
   - Registering a user when capacity is available
   - Renewing a subscription with a new end date

2. **Edge Cases**: Boundary conditions and special scenarios
   - Service with exactly 0 active users
   - Service at exactly user_limit capacity
   - Subscription expiring today
   - Empty result sets

3. **Error Conditions**: Specific error scenarios
   - Creating service with user_limit = 0
   - Registering user for expired subscription
   - Extending subscription with new_limit < active_count

4. **Integration Points**: Component interactions
   - API endpoint routing to correct controllers
   - Controller calling appropriate service methods
   - Service methods interacting with repositories

**Unit Test Framework**: PHPUnit for backend, Jest for frontend JavaScript

**Example Unit Tests**:
```php
// Test specific example
testCreateClientWithValidData()
testRegisterUserWhenCapacityAvailable()

// Test edge cases
testServiceWithZeroActiveUsers()
testServiceAtExactCapacity()

// Test error conditions
testRejectUserLimitZero()
testRejectUserForExpiredSubscription()
```

### Property-Based Testing

Property tests will verify universal properties across randomized inputs. Each property test will:

- Run a minimum of 100 iterations with randomized inputs
- Reference the design document property it validates
- Use tags in the format: **Feature: subscription-management-module, Property {number}: {property_text}**

**Property Test Framework**: We will use **Pest with Pest Property Plugin** for PHP property-based testing.

**Property Test Configuration**:
```php
// Configure minimum iterations
uses()->property()->iterations(100);
```

**Example Property Tests**:

```php
/**
 * Feature: subscription-management-module, Property 2: Entity Persistence Round-Trip
 * For any client, project, or service entity, creating it and then retrieving it 
 * by ID should return an equivalent entity with all fields matching the original values.
 */
test('entity persistence round-trip', function () {
    property()
        ->forAll(
            Generator::client(),
            Generator::project(),
            Generator::service()
        )
        ->then(function ($client, $project, $service) {
            // Test client round-trip
            $clientId = $this->clientRepo->create($client);
            $retrieved = $this->clientRepo->findById($clientId);
            expect($retrieved)->toEqual($client);
            
            // Test project round-trip
            $projectId = $this->projectRepo->create($project);
            $retrieved = $this->projectRepo->findById($projectId);
            expect($retrieved)->toEqual($project);
            
            // Test service round-trip
            $serviceId = $this->serviceRepo->create($service);
            $retrieved = $this->serviceRepo->findById($serviceId);
            expect($retrieved)->toEqual($service);
        })
        ->iterations(100);
});

/**
 * Feature: subscription-management-module, Property 11: Validation Correctness
 * For any service, validation should return success if and only if 
 * active_user_count < user_limit AND the subscription is active.
 */
test('validation correctness', function () {
    property()
        ->forAll(
            Generator::service(),
            Generator::int(0, 100), // active_user_count
            Generator::date() // current_date
        )
        ->then(function ($service, $activeCount, $currentDate) {
            $service->active_user_count = $activeCount;
            
            $isActive = $currentDate >= $service->start_date 
                     && $currentDate <= $service->end_date;
            $hasCapacity = $activeCount < $service->user_limit;
            
            $result = $this->validator->validateUserCreation($service, $currentDate);
            
            expect($result->isValid())->toBe($isActive && $hasCapacity);
        })
        ->iterations(100);
});

/**
 * Feature: subscription-management-module, Property 35: Concurrent User Creation Safety
 * For any service at exactly (user_limit - 1) active users, if N concurrent 
 * user creation requests arrive (where N > 1), at most 1 should succeed.
 */
test('concurrent user creation safety', function () {
    property()
        ->forAll(
            Generator::service(),
            Generator::int(2, 10) // number of concurrent requests
        )
        ->then(function ($service, $concurrentRequests) {
            // Set service to one below capacity
            $service->active_user_count = $service->user_limit - 1;
            $this->serviceRepo->update($service);
            
            // Simulate concurrent requests
            $promises = [];
            for ($i = 0; $i < $concurrentRequests; $i++) {
                $promises[] = async(fn() => 
                    $this->userService->registerUser($service->id, "user_$i")
                );
            }
            
            $results = await($promises);
            $successCount = count(array_filter($results, fn($r) => $r->isSuccess()));
            
            expect($successCount)->toBeLessThanOrEqual(1);
        })
        ->iterations(100);
});
```

### Test Data Generators

Property tests require generators for creating random valid test data:

```php
class Generator {
    public static function client(): Gen {
        return Gen::map(
            fn($name, $contact) => new Client(null, $name, $contact),
            Gen::string(1, 255),
            Gen::string(0, 1000)
        );
    }
    
    public static function service(): Gen {
        return Gen::map(
            fn($projectId, $type, $limit, $start, $end) => 
                new Service(null, $projectId, $type, $limit, 0, $start, $end),
            Gen::int(1, 1000), // project_id
            Gen::elements(['web', 'mobile', 'other']),
            Gen::int(1, 1000), // user_limit
            Gen::date(),
            Gen::date()
        )->filter(fn($s) => $s->end_date >= $s->start_date);
    }
}
```

### Test Coverage Goals

- **Unit Test Coverage**: Minimum 80% code coverage
- **Property Test Coverage**: All 41 correctness properties implemented
- **Integration Test Coverage**: All API endpoints tested
- **Frontend Test Coverage**: All UI components and interactions tested

### Testing Phases

1. **Unit Testing Phase**: Test individual components in isolation
2. **Property Testing Phase**: Verify universal properties with randomized inputs
3. **Integration Testing Phase**: Test API endpoints end-to-end
4. **Frontend Testing Phase**: Test UI components and user interactions
5. **System Testing Phase**: Test complete workflows across all layers
6. **Performance Testing Phase**: Verify validation completes within 500ms
7. **Concurrency Testing Phase**: Verify race condition handling under load

### Continuous Integration

- Run all unit tests on every commit
- Run property tests (100 iterations) on every pull request
- Run full test suite (1000 iterations for property tests) nightly
- Fail builds if any test fails or coverage drops below threshold

