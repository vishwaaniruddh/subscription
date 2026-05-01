# Subscription Management Module - API Documentation

## Overview

The Subscription Management Module provides a RESTful API for managing client subscriptions with hierarchical organization (Client → Project → Service) and enforces user limits per service. All endpoints accept and return JSON data.

**Base URL**: `/api`

**API Version**: 1.0

## Table of Contents

1. [Authentication](#authentication)
2. [Response Format](#response-format)
3. [Error Codes](#error-codes)
4. [Client Management](#client-management)
5. [Project Management](#project-management)
6. [Service Management](#service-management)
7. [User Validation & Management](#user-validation--management)
8. [Subscription Lifecycle](#subscription-lifecycle)
9. [Reporting & Analytics](#reporting--analytics)

## Authentication

Currently, the API does not require authentication. All endpoints are publicly accessible.

## Response Format

### Success Response

All successful responses return JSON with appropriate HTTP status codes:

- `200 OK` - Successful GET, PUT, DELETE operations
- `201 Created` - Successful POST operations creating new resources

```json
{
  "id": 1,
  "name": "Example Client",
  "contact_info": "contact@example.com",
  "created_at": "2024-01-15T10:30:00Z"
}
```

### Error Response

All error responses follow this structure:

```json
{
  "success": false,
  "error": "Human-readable error message",
  "error_code": "ERROR_CODE",
  "context": {
    "field": "additional context"
  }
}
```

## Error Codes

| Error Code | HTTP Status | Description | Context Fields |
|------------|-------------|-------------|----------------|
| `USER_LIMIT_EXCEEDED` | 400 | Active user count at or above limit | `current_count`, `user_limit` |
| `SUBSCRIPTION_EXPIRED` | 400 | Current date after subscription end date | `expiry_date`, `current_date` |
| `INVALID_USER_LIMIT` | 400 | New user limit below active user count | `active_user_count`, `requested_limit` |
| `INVALID_DATE_RANGE` | 400 | Start date after end date | `start_date`, `end_date` |
| `MISSING_REQUIRED_FIELD` | 400 | Required field not provided | `field_name` |
| `INVALID_FIELD_TYPE` | 400 | Field has wrong data type | `field_name`, `expected_type`, `actual_type` |
| `RESOURCE_NOT_FOUND` | 404 | Entity does not exist | `resource_type`, `resource_id` |
| `FOREIGN_KEY_VIOLATION` | 409 | Referenced entity does not exist | `field_name`, `referenced_id` |
| `DATABASE_ERROR` | 500 | Database operation failed | `error_message` |
| `TRANSACTION_FAILED` | 500 | Transaction could not complete after retries | `retry_count` |

---

## Client Management

### List All Clients

Retrieve a list of all clients in the system.

**Endpoint**: `GET /api/clients`

**Request**: No parameters required

**Response**: `200 OK`

```json
[
  {
    "id": 1,
    "name": "Acme Corporation",
    "contact_info": "contact@acme.com",
    "created_at": "2024-01-15T10:30:00Z",
    "updated_at": "2024-01-15T10:30:00Z"
  },
  {
    "id": 2,
    "name": "TechStart Inc",
    "contact_info": "info@techstart.com",
    "created_at": "2024-01-16T14:20:00Z",
    "updated_at": "2024-01-16T14:20:00Z"
  }
]
```

**cURL Example**:
```bash
curl -X GET http://localhost/api/clients
```

### Get Client by ID

Retrieve details of a specific client.

**Endpoint**: `GET /api/clients/{id}`

**Path Parameters**:
- `id` (integer, required) - Client ID

**Response**: `200 OK`

```json
{
  "id": 1,
  "name": "Acme Corporation",
  "contact_info": "contact@acme.com",
  "created_at": "2024-01-15T10:30:00Z",
  "updated_at": "2024-01-15T10:30:00Z"
}
```

**Error Response**: `404 Not Found`

```json
{
  "success": false,
  "error": "Client not found"
}
```

**cURL Example**:
```bash
curl -X GET http://localhost/api/clients/1
```

### Create Client

Create a new client.

**Endpoint**: `POST /api/clients`

**Request Body**:

```json
{
  "name": "Acme Corporation",
  "contact_info": "contact@acme.com"
}
```

**Request Fields**:
- `name` (string, required) - Client name (max 255 characters)
- `contact_info` (string, optional) - Contact information

**Response**: `201 Created`

```json
{
  "id": 1,
  "name": "Acme Corporation",
  "contact_info": "contact@acme.com",
  "created_at": "2024-01-15T10:30:00Z"
}
```

**cURL Example**:
```bash
curl -X POST http://localhost/api/clients \
  -H "Content-Type: application/json" \
  -d '{"name":"Acme Corporation","contact_info":"contact@acme.com"}'
```

### Update Client

Update an existing client's information.

**Endpoint**: `PUT /api/clients/{id}`

**Path Parameters**:
- `id` (integer, required) - Client ID

**Request Body**:

```json
{
  "name": "Acme Corporation Updated",
  "contact_info": "newcontact@acme.com"
}
```

**Response**: `200 OK`

```json
{
  "success": true
}
```

**cURL Example**:
```bash
curl -X PUT http://localhost/api/clients/1 \
  -H "Content-Type: application/json" \
  -d '{"name":"Acme Corporation Updated","contact_info":"newcontact@acme.com"}'
```

### Delete Client

Delete a client and all associated projects, services, and users.

**Endpoint**: `DELETE /api/clients/{id}`

**Path Parameters**:
- `id` (integer, required) - Client ID

**Response**: `200 OK`

```json
{
  "success": true
}
```

**cURL Example**:
```bash
curl -X DELETE http://localhost/api/clients/1
```

---

## Project Management

### List Projects by Client

Retrieve all projects for a specific client.

**Endpoint**: `GET /api/clients/{clientId}/projects`

**Path Parameters**:
- `clientId` (integer, required) - Client ID

**Response**: `200 OK`

```json
[
  {
    "id": 1,
    "client_id": 1,
    "name": "Mobile App Project",
    "description": "iOS and Android mobile application",
    "created_at": "2024-01-15T11:00:00Z",
    "updated_at": "2024-01-15T11:00:00Z"
  }
]
```

**cURL Example**:
```bash
curl -X GET http://localhost/api/clients/1/projects
```

### Get Project by ID

Retrieve details of a specific project.

**Endpoint**: `GET /api/projects/{id}`

**Path Parameters**:
- `id` (integer, required) - Project ID

**Response**: `200 OK`

```json
{
  "id": 1,
  "client_id": 1,
  "name": "Mobile App Project",
  "description": "iOS and Android mobile application",
  "created_at": "2024-01-15T11:00:00Z",
  "updated_at": "2024-01-15T11:00:00Z"
}
```

**Error Response**: `404 Not Found`

```json
{
  "success": false,
  "error": "Project not found"
}
```

**cURL Example**:
```bash
curl -X GET http://localhost/api/projects/1
```

### Create Project

Create a new project associated with a client.

**Endpoint**: `POST /api/projects`

**Request Body**:

```json
{
  "client_id": 1,
  "name": "Mobile App Project",
  "description": "iOS and Android mobile application"
}
```

**Request Fields**:
- `client_id` (integer, required) - ID of the parent client
- `name` (string, required) - Project name (max 255 characters)
- `description` (string, optional) - Project description

**Response**: `201 Created`

```json
{
  "id": 1,
  "client_id": 1,
  "name": "Mobile App Project",
  "description": "iOS and Android mobile application",
  "created_at": "2024-01-15T11:00:00Z"
}
```

**cURL Example**:
```bash
curl -X POST http://localhost/api/projects \
  -H "Content-Type: application/json" \
  -d '{"client_id":1,"name":"Mobile App Project","description":"iOS and Android mobile application"}'
```

### Update Project

Update an existing project's information.

**Endpoint**: `PUT /api/projects/{id}`

**Path Parameters**:
- `id` (integer, required) - Project ID

**Request Body**:

```json
{
  "name": "Mobile App Project Updated",
  "description": "Updated description"
}
```

**Response**: `200 OK`

```json
{
  "success": true
}
```

**cURL Example**:
```bash
curl -X PUT http://localhost/api/projects/1 \
  -H "Content-Type: application/json" \
  -d '{"name":"Mobile App Project Updated","description":"Updated description"}'
```

### Delete Project

Delete a project and all associated services and users.

**Endpoint**: `DELETE /api/projects/{id}`

**Path Parameters**:
- `id` (integer, required) - Project ID

**Response**: `200 OK`

```json
{
  "success": true
}
```

**cURL Example**:
```bash
curl -X DELETE http://localhost/api/projects/1
```

---

## Service Management

### List Services by Project

Retrieve all services for a specific project.

**Endpoint**: `GET /api/projects/{projectId}/services`

**Path Parameters**:
- `projectId` (integer, required) - Project ID

**Response**: `200 OK`

```json
[
  {
    "id": 1,
    "project_id": 1,
    "service_type": "web",
    "user_limit": 100,
    "active_user_count": 45,
    "start_date": "2024-01-01",
    "end_date": "2024-12-31",
    "created_at": "2024-01-15T12:00:00Z",
    "updated_at": "2024-01-15T12:00:00Z"
  }
]
```

**cURL Example**:
```bash
curl -X GET http://localhost/api/projects/1/services
```

### Get Service by ID

Retrieve details of a specific service.

**Endpoint**: `GET /api/services/{id}`

**Path Parameters**:
- `id` (integer, required) - Service ID

**Response**: `200 OK`

```json
{
  "id": 1,
  "project_id": 1,
  "service_type": "web",
  "user_limit": 100,
  "active_user_count": 45,
  "start_date": "2024-01-01",
  "end_date": "2024-12-31",
  "status": "active",
  "created_at": "2024-01-15T12:00:00Z",
  "updated_at": "2024-01-15T12:00:00Z"
}
```

**Error Response**: `404 Not Found`

```json
{
  "success": false,
  "error": "Service not found"
}
```

**cURL Example**:
```bash
curl -X GET http://localhost/api/services/1
```

### Create Service

Create a new service within a project.

**Endpoint**: `POST /api/services`

**Request Body**:

```json
{
  "project_id": 1,
  "service_type": "web",
  "user_limit": 100,
  "start_date": "2024-01-01",
  "end_date": "2024-12-31"
}
```

**Request Fields**:
- `project_id` (integer, required) - ID of the parent project
- `service_type` (string, required) - Type of service: `web`, `mobile`, or `other`
- `user_limit` (integer, required) - Maximum number of users (must be > 0)
- `start_date` (string, required) - Subscription start date (ISO 8601 format: YYYY-MM-DD)
- `end_date` (string, required) - Subscription end date (ISO 8601 format: YYYY-MM-DD, must be >= start_date)

**Response**: `201 Created`

```json
{
  "id": 1,
  "project_id": 1,
  "service_type": "web",
  "user_limit": 100,
  "active_user_count": 0,
  "start_date": "2024-01-01",
  "end_date": "2024-12-31",
  "created_at": "2024-01-15T12:00:00Z"
}
```

**cURL Example**:
```bash
curl -X POST http://localhost/api/services \
  -H "Content-Type: application/json" \
  -d '{"project_id":1,"service_type":"web","user_limit":100,"start_date":"2024-01-01","end_date":"2024-12-31"}'
```

### Update Service

Update an existing service's information.

**Endpoint**: `PUT /api/services/{id}`

**Path Parameters**:
- `id` (integer, required) - Service ID

**Request Body**:

```json
{
  "service_type": "mobile",
  "user_limit": 150,
  "start_date": "2024-01-01",
  "end_date": "2024-12-31"
}
```

**Response**: `200 OK`

```json
{
  "success": true
}
```

**cURL Example**:
```bash
curl -X PUT http://localhost/api/services/1 \
  -H "Content-Type: application/json" \
  -d '{"service_type":"mobile","user_limit":150}'
```

### Delete Service

Delete a service and all associated users.

**Endpoint**: `DELETE /api/services/{id}`

**Path Parameters**:
- `id` (integer, required) - Service ID

**Response**: `200 OK`

```json
{
  "success": true
}
```

**cURL Example**:
```bash
curl -X DELETE http://localhost/api/services/1
```

---

## User Validation & Management

### Validate User Creation

Check if a new user can be created for a service without exceeding limits.

**Endpoint**: `POST /api/services/{serviceId}/validate-user`

**Path Parameters**:
- `serviceId` (integer, required) - Service ID

**Request Body**:

```json
{
  "user_identifier": "user@example.com"
}
```

**Request Fields**:
- `user_identifier` (string, optional) - User identifier for validation

**Success Response**: `200 OK`

```json
{
  "success": true,
  "service_id": 1,
  "current_count": 45,
  "user_limit": 100
}
```

**Performance**: This endpoint completes within 500 milliseconds.

**Error Response - User Limit Exceeded**: `400 Bad Request`

```json
{
  "success": false,
  "error": "User limit exceeded",
  "error_code": "USER_LIMIT_EXCEEDED",
  "context": {
    "current_count": 100,
    "user_limit": 100
  }
}
```

**Error Response - Subscription Expired**: `400 Bad Request`

```json
{
  "success": false,
  "error": "Subscription has expired",
  "error_code": "SUBSCRIPTION_EXPIRED",
  "context": {
    "expiry_date": "2024-01-15",
    "current_date": "2024-01-20"
  }
}
```

**cURL Example**:
```bash
curl -X POST http://localhost/api/services/1/validate-user \
  -H "Content-Type: application/json" \
  -d '{"user_identifier":"user@example.com"}'
```

### Register User

Register a new active user for a service.

**Endpoint**: `POST /api/services/{serviceId}/users`

**Path Parameters**:
- `serviceId` (integer, required) - Service ID

**Request Body**:

```json
{
  "service_id": 1,
  "user_identifier": "user@example.com"
}
```

**Request Fields**:
- `service_id` (integer, required) - Service ID
- `user_identifier` (string, required) - Unique user identifier (max 255 characters)

**Response**: `201 Created`

```json
{
  "id": 1,
  "service_id": 1,
  "user_identifier": "user@example.com",
  "status": "active",
  "created_at": "2024-01-15T13:00:00Z"
}
```

**Error Response - Limit Exceeded**: `400 Bad Request`

```json
{
  "success": false,
  "error": "Cannot register user: limit exceeded",
  "error_code": "USER_LIMIT_EXCEEDED"
}
```

**cURL Example**:
```bash
curl -X POST http://localhost/api/services/1/users \
  -H "Content-Type: application/json" \
  -d '{"service_id":1,"user_identifier":"user@example.com"}'
```

### List Active Users

Retrieve all active users for a specific service.

**Endpoint**: `GET /api/services/{serviceId}/users`

**Path Parameters**:
- `serviceId` (integer, required) - Service ID

**Response**: `200 OK`

```json
[
  {
    "id": 1,
    "service_id": 1,
    "user_identifier": "user1@example.com",
    "status": "active",
    "created_at": "2024-01-15T13:00:00Z",
    "deactivated_at": null
  },
  {
    "id": 2,
    "service_id": 1,
    "user_identifier": "user2@example.com",
    "status": "active",
    "created_at": "2024-01-15T14:00:00Z",
    "deactivated_at": null
  }
]
```

**cURL Example**:
```bash
curl -X GET http://localhost/api/services/1/users
```

### Deactivate User

Deactivate an active user, freeing up subscription capacity.

**Endpoint**: `DELETE /api/users/{userId}`

**Path Parameters**:
- `userId` (integer, required) - User ID

**Response**: `200 OK`

```json
{
  "success": true
}
```

**Error Response**: `404 Not Found`

```json
{
  "success": false,
  "error": "User not found or already deactivated"
}
```

**cURL Example**:
```bash
curl -X DELETE http://localhost/api/users/1
```

---

## Subscription Lifecycle

### Get Subscription Status

Retrieve the current status of a service subscription.

**Endpoint**: `GET /api/services/{serviceId}/status`

**Path Parameters**:
- `serviceId` (integer, required) - Service ID

**Response**: `200 OK`

```json
{
  "id": 1,
  "status": "active",
  "user_limit": 100,
  "active_user_count": 45,
  "start_date": "2024-01-01",
  "end_date": "2024-12-31"
}
```

**Status Values**:
- `active` - Current date is between start_date and end_date
- `expired` - Current date is after end_date

**cURL Example**:
```bash
curl -X GET http://localhost/api/services/1/status
```

### Renew Subscription

Extend a subscription by updating the end date.

**Endpoint**: `POST /api/services/{serviceId}/renew`

**Path Parameters**:
- `serviceId` (integer, required) - Service ID

**Request Body**:

```json
{
  "new_end_date": "2025-12-31"
}
```

**Request Fields**:
- `new_end_date` (string, required) - New subscription end date (ISO 8601 format: YYYY-MM-DD)

**Response**: `200 OK`

```json
{
  "success": true
}
```

**Notes**:
- User limit and active user count remain unchanged
- A history record is created with action_type='renewal'

**cURL Example**:
```bash
curl -X POST http://localhost/api/services/1/renew \
  -H "Content-Type: application/json" \
  -d '{"new_end_date":"2025-12-31"}'
```

### Extend Subscription

Increase the user limit and optionally extend the end date.

**Endpoint**: `POST /api/services/{serviceId}/extend`

**Path Parameters**:
- `serviceId` (integer, required) - Service ID

**Request Body**:

```json
{
  "new_user_limit": 150,
  "new_end_date": "2025-12-31"
}
```

**Request Fields**:
- `new_user_limit` (integer, required) - New user limit (must be >= active_user_count)
- `new_end_date` (string, optional) - New subscription end date (ISO 8601 format: YYYY-MM-DD)

**Response**: `200 OK`

```json
{
  "success": true
}
```

**Error Response - Invalid Limit**: `400 Bad Request`

```json
{
  "success": false,
  "error": "New user limit cannot be less than active user count",
  "error_code": "INVALID_USER_LIMIT",
  "context": {
    "active_user_count": 45,
    "requested_limit": 30
  }
}
```

**Notes**:
- A history record is created with action_type='extension'

**cURL Example**:
```bash
curl -X POST http://localhost/api/services/1/extend \
  -H "Content-Type: application/json" \
  -d '{"new_user_limit":150,"new_end_date":"2025-12-31"}'
```

---

## Reporting & Analytics

### Get Client Utilization

Retrieve utilization statistics for all services under a client.

**Endpoint**: `GET /api/clients/{clientId}/utilization`

**Path Parameters**:
- `clientId` (integer, required) - Client ID

**Response**: `200 OK`

```json
[
  {
    "id": 1,
    "project_id": 1,
    "service_type": "web",
    "user_limit": 100,
    "active_user_count": 45,
    "utilization_percentage": 45.0,
    "start_date": "2024-01-01",
    "end_date": "2024-12-31",
    "status": "active"
  },
  {
    "id": 2,
    "project_id": 1,
    "service_type": "mobile",
    "user_limit": 50,
    "active_user_count": 48,
    "utilization_percentage": 96.0,
    "start_date": "2024-01-01",
    "end_date": "2024-12-31",
    "status": "active"
  }
]
```

**cURL Example**:
```bash
curl -X GET http://localhost/api/clients/1/utilization
```

### Get Project Utilization

Retrieve utilization statistics for all services under a project.

**Endpoint**: `GET /api/projects/{projectId}/utilization`

**Path Parameters**:
- `projectId` (integer, required) - Project ID

**Response**: `200 OK`

```json
[
  {
    "id": 1,
    "project_id": 1,
    "service_type": "web",
    "user_limit": 100,
    "active_user_count": 45,
    "utilization_percentage": 45.0,
    "start_date": "2024-01-01",
    "end_date": "2024-12-31",
    "status": "active"
  }
]
```

**cURL Example**:
```bash
curl -X GET http://localhost/api/projects/1/utilization
```

### Get Service Utilization

Retrieve utilization statistics for a specific service.

**Endpoint**: `GET /api/services/{serviceId}/utilization`

**Path Parameters**:
- `serviceId` (integer, required) - Service ID

**Response**: `200 OK`

```json
{
  "id": 1,
  "project_id": 1,
  "service_type": "web",
  "user_limit": 100,
  "active_user_count": 45,
  "utilization_percentage": 45.0,
  "start_date": "2024-01-01",
  "end_date": "2024-12-31",
  "status": "active"
}
```

**Utilization Calculation**: `(active_user_count / user_limit) × 100`

**Error Response**: `404 Not Found`

```json
{
  "success": false,
  "error": "Service not found"
}
```

**cURL Example**:
```bash
curl -X GET http://localhost/api/services/1/utilization
```

### Get Expiring Services

Retrieve services with subscriptions expiring within a specified number of days.

**Endpoint**: `GET /api/services/expiring?days={days}`

**Query Parameters**:
- `days` (integer, optional) - Number of days to look ahead (default: 30)

**Response**: `200 OK`

```json
[
  {
    "id": 3,
    "project_id": 2,
    "service_type": "web",
    "user_limit": 75,
    "active_user_count": 60,
    "utilization_percentage": 80.0,
    "start_date": "2024-01-01",
    "end_date": "2024-02-15",
    "status": "active",
    "days_until_expiry": 10
  }
]
```

**cURL Example**:
```bash
curl -X GET "http://localhost/api/services/expiring?days=30"
```

### Get High Utilization Services

Retrieve services with utilization above a specified threshold.

**Endpoint**: `GET /api/services/high-utilization?threshold={percentage}`

**Query Parameters**:
- `threshold` (float, optional) - Utilization percentage threshold (default: 90.0)

**Response**: `200 OK`

```json
[
  {
    "id": 2,
    "project_id": 1,
    "service_type": "mobile",
    "user_limit": 50,
    "active_user_count": 48,
    "utilization_percentage": 96.0,
    "start_date": "2024-01-01",
    "end_date": "2024-12-31",
    "status": "active"
  }
]
```

**cURL Example**:
```bash
curl -X GET "http://localhost/api/services/high-utilization?threshold=90"
```

---

## Common HTTP Status Codes

| Status Code | Description | Usage |
|-------------|-------------|-------|
| `200 OK` | Request succeeded | GET, PUT, DELETE operations |
| `201 Created` | Resource created successfully | POST operations |
| `400 Bad Request` | Invalid request data or business rule violation | Validation errors, limit exceeded, expired subscription |
| `404 Not Found` | Resource does not exist | Invalid ID in GET, PUT, DELETE |
| `409 Conflict` | Resource conflict | Foreign key violations, duplicate entries |
| `500 Internal Server Error` | Server error | Database errors, unexpected exceptions |

---

## CORS Support

All endpoints support Cross-Origin Resource Sharing (CORS) with the following headers:

```
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization
```

Preflight `OPTIONS` requests are automatically handled by the API.

---

## Data Validation Rules

### Client
- `name`: Required, max 255 characters
- `contact_info`: Optional, text

### Project
- `client_id`: Required, must reference existing client
- `name`: Required, max 255 characters
- `description`: Optional, text

### Service
- `project_id`: Required, must reference existing project
- `service_type`: Required, one of: `web`, `mobile`, `other`
- `user_limit`: Required, positive integer > 0
- `start_date`: Required, ISO 8601 date format (YYYY-MM-DD)
- `end_date`: Required, ISO 8601 date format (YYYY-MM-DD), must be >= start_date

### User
- `service_id`: Required, must reference existing service
- `user_identifier`: Required, max 255 characters
- Must not duplicate active user for same service

### Subscription Operations
- **Renewal**: `new_end_date` must be provided
- **Extension**: `new_user_limit` must be >= active_user_count

---

## Concurrency and Data Integrity

The API implements the following mechanisms to ensure data integrity:

1. **Transaction Isolation**: Uses `READ COMMITTED` isolation level
2. **Row-Level Locking**: Uses `SELECT ... FOR UPDATE` when updating user counts
3. **Atomic Operations**: User registration wraps validation + increment in single transaction
4. **Retry Logic**: Implements exponential backoff for deadlock retries (max 3 attempts)

This ensures that even under concurrent load, user limits are never exceeded.

---

## Performance Considerations

- **Validation Endpoint**: Completes within 500 milliseconds
- **Database Indexes**: Optimized queries on frequently accessed fields
- **Connection Pooling**: Efficient database connection management

---

## Example Workflows

### Complete Client Setup Workflow

```bash
# 1. Create a client
curl -X POST http://localhost/api/clients \
  -H "Content-Type: application/json" \
  -d '{"name":"Acme Corp","contact_info":"contact@acme.com"}'
# Response: {"id":1,...}

# 2. Create a project for the client
curl -X POST http://localhost/api/projects \
  -H "Content-Type: application/json" \
  -d '{"client_id":1,"name":"Web Portal","description":"Customer portal"}'
# Response: {"id":1,...}

# 3. Create a service for the project
curl -X POST http://localhost/api/services \
  -H "Content-Type: application/json" \
  -d '{"project_id":1,"service_type":"web","user_limit":100,"start_date":"2024-01-01","end_date":"2024-12-31"}'
# Response: {"id":1,...}

# 4. Validate user creation
curl -X POST http://localhost/api/services/1/validate-user \
  -H "Content-Type: application/json" \
  -d '{"user_identifier":"user@example.com"}'
# Response: {"success":true,...}

# 5. Register a user
curl -X POST http://localhost/api/services/1/users \
  -H "Content-Type: application/json" \
  -d '{"service_id":1,"user_identifier":"user@example.com"}'
# Response: {"id":1,...}
```

### Subscription Management Workflow

```bash
# 1. Check subscription status
curl -X GET http://localhost/api/services/1/status
# Response: {"status":"active",...}

# 2. Renew subscription
curl -X POST http://localhost/api/services/1/renew \
  -H "Content-Type: application/json" \
  -d '{"new_end_date":"2025-12-31"}'
# Response: {"success":true}

# 3. Extend user limit
curl -X POST http://localhost/api/services/1/extend \
  -H "Content-Type: application/json" \
  -d '{"new_user_limit":150}'
# Response: {"success":true}
```

### Reporting Workflow

```bash
# 1. Check service utilization
curl -X GET http://localhost/api/services/1/utilization
# Response: {"utilization_percentage":45.0,...}

# 2. Find expiring subscriptions
curl -X GET "http://localhost/api/services/expiring?days=30"
# Response: [...]

# 3. Find high utilization services
curl -X GET "http://localhost/api/services/high-utilization?threshold=80"
# Response: [...]
```

---

## Support and Contact

For API support or questions, please contact the development team.

**API Version**: 1.0  
**Last Updated**: 2024-01-20
