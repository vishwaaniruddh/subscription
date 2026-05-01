# Implementation Plan: Subscription Management Module

## Overview

This implementation plan breaks down the Subscription Management Module into discrete coding tasks. The system follows a three-tier architecture with PHP backend, MySQL database, and vanilla JavaScript frontend. Tasks are organized to build incrementally, validating core functionality early through automated tests.

## Tasks

- [x] 1. Set up project structure and database schema
  - [x] 1.1 Create directory structure and configuration files
    - Create `src/` directory with subdirectories: `Controllers/`, `Services/`, `Repositories/`, `Models/`, `Exceptions/`
    - Create `public/` directory for frontend assets: `js/`, `css/`
    - Create `config/` directory for database and application configuration
    - Create `tests/` directory with subdirectories: `Unit/`, `Property/`, `Integration/`
    - Set up Composer with dependencies: PHPUnit, Pest, Pest Property Plugin
    - Create `.env` file for database credentials
    - _Requirements: 11.1_

  - [x] 1.2 Create MySQL database schema
    - Implement SQL migration file with all five tables: clients, projects, services, users, subscription_history
    - Add foreign key constraints with CASCADE delete
    - Add indexes on frequently queried fields
    - Add CHECK constraints for data validation (user_limit > 0, active_user_count >= 0, end_date >= start_date)
    - _Requirements: 11.2, 11.3, 11.4, 11.5, 11.6, 11.7, 11.8_

  - [x] 1.3 Write property test for database schema constraints
    - **Property 6: User Limit Validation**
    - **Validates: Requirements 4.1, 4.2**

- [x] 2. Implement data access layer (repositories)
  - [x] 2.1 Create base repository with PDO connection
    - Implement `BaseRepository` class with PDO instance
    - Add transaction management methods (beginTransaction, commit, rollback)
    - Add retry logic for deadlock handling (max 3 attempts with exponential backoff)
    - _Requirements: 15.2, 15.5_

  - [x] 2.2 Implement ClientRepository
    - Create `ClientRepository` class with CRUD methods: create, findById, findAll, update, delete
    - Use prepared statements for all queries
    - _Requirements: 1.1, 1.2, 1.3, 1.4_

  - [x] 2.3 Write property test for entity persistence round-trip
    - **Property 2: Entity Persistence Round-Trip**
    - **Validates: Requirements 1.6**

  - [x] 2.4 Implement ProjectRepository
    - Create `ProjectRepository` class with CRUD methods: create, findById, findByClientId, update, delete
    - Enforce foreign key relationship with clients table
    - _Requirements: 2.1, 2.2, 2.3, 2.4_

  - [x] 2.5 Write property test for client-project relationship
    - **Property 3: Client-Project Relationship**
    - **Validates: Requirements 2.6**

  - [x] 2.6 Implement ServiceRepository
    - Create `ServiceRepository` class with CRUD methods: create, findById, findByProjectId, update, delete
    - Add method `findExpiring(int $days)` for expiring subscriptions query
    - Add method `findHighUtilization(float $threshold)` for high utilization query
    - Implement row-level locking with `SELECT ... FOR UPDATE` for concurrent access
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 14.5, 14.6, 15.3_

  - [x] 2.7 Write property test for project-service relationship
    - **Property 4: Project-Service Relationship**
    - **Validates: Requirements 3.7**

  - [x] 2.8 Implement UserRepository
    - Create `UserRepository` class with methods: create, findById, findByServiceId, deactivate
    - Add method `countActiveByServiceId(int $serviceId)` for active user count
    - _Requirements: 5.6, 6.1, 6.4_

  - [x] 2.9 Implement SubscriptionHistoryRepository
    - Create `SubscriptionHistoryRepository` class with method: create, findByServiceId
    - _Requirements: 8.6, 9.6_

- [x] 3. Implement domain models
  - [x] 3.1 Create Client model
    - Implement `Client` class with properties: id, name, contactInfo, createdAt, updatedAt
    - Add validation methods for required fields
    - _Requirements: 1.5_

  - [x] 3.2 Create Project model
    - Implement `Project` class with properties: id, clientId, name, description, createdAt, updatedAt
    - Add validation methods for required fields and foreign key
    - _Requirements: 2.5_

  - [x] 3.3 Create Service model
    - Implement `Service` class with properties: id, projectId, serviceType, userLimit, activeUserCount, startDate, endDate, createdAt, updatedAt
    - Add method `isActive(): bool` to check subscription status
    - Add method `canAddUser(): bool` to check capacity
    - Add method `getUtilizationPercentage(): float` for utilization calculation
    - _Requirements: 3.5, 7.5, 7.6, 14.4_

  - [x] 3.4 Write property tests for Service model methods
    - **Property 15: Active Subscription Status**
    - **Property 16: Expired Subscription Status**
    - **Property 32: Utilization Calculation**
    - **Validates: Requirements 7.5, 7.6, 14.4**

  - [x] 3.5 Create User model
    - Implement `User` class with properties: id, serviceId, userIdentifier, status, createdAt, deactivatedAt
    - _Requirements: 5.6_

  - [x] 3.6 Create SubscriptionHistory model
    - Implement `SubscriptionHistory` class with properties: id, serviceId, actionType, oldValue, newValue, timestamp
    - _Requirements: 8.6, 9.6_

- [x] 4. Implement business logic layer (services)
  - [x] 4.1 Create ClientService
    - Implement `ClientService` class with methods: createClient, getClient, updateClient, deleteClient, listClients
    - Add input validation for name and contact_info
    - _Requirements: 1.1, 1.2, 1.3, 1.4_

  - [x] 4.2 Write property test for unique identifier assignment
    - **Property 1: Entity Creation Assigns Unique Identifiers**
    - **Validates: Requirements 1.5**

  - [x] 4.3 Create ProjectService
    - Implement `ProjectService` class with methods: createProject, getProject, updateProject, deleteProject, listProjectsByClient
    - Add input validation and foreign key verification
    - _Requirements: 2.1, 2.2, 2.3, 2.4_

  - [x] 4.4 Write property test for foreign key integrity
    - **Property 31: Foreign Key Integrity**
    - **Validates: Requirements 11.7**

  - [x] 4.5 Create ServiceManager
    - Implement `ServiceManager` class with methods: createService, getService, updateService, deleteService, listServicesByProject
    - Add validation for service_type (must be in ['web', 'mobile', 'other'])
    - Add validation for date range (end_date >= start_date)
    - Add validation for user_limit (must be > 0)
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 4.1, 4.2, 7.1, 7.2_

  - [x] 4.6 Write property tests for service validation
    - **Property 5: Valid Service Types**
    - **Property 13: Subscription Date Requirements**
    - **Validates: Requirements 3.6, 7.1, 7.2**

  - [x] 4.7 Create ValidationService
    - Implement `ValidationService` class with method: validateUserCreation(int $serviceId, string $currentDate)
    - Check if subscription is active (current date between start_date and end_date)
    - Check if capacity is available (active_user_count < user_limit)
    - Return validation result with error code and context
    - Ensure validation completes within 500ms
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5_

  - [x] 4.8 Write property test for validation correctness
    - **Property 11: Validation Correctness**
    - **Validates: Requirements 5.2, 5.3, 5.4, 7.7**

  - [x] 4.9 Create UserService
    - Implement `UserService` class with methods: registerUser, deactivateUser, listActiveUsers
    - Implement registerUser with transaction and row-level locking
    - Increment active_user_count on successful registration
    - Decrement active_user_count on deactivation
    - Reject registration if active_user_count >= user_limit
    - _Requirements: 5.6, 5.7, 5.8, 6.1, 6.2, 6.4_

  - [x] 4.10 Write property tests for user management
    - **Property 7: Active User Count Accuracy**
    - **Property 8: User Registration Increments Count**
    - **Property 9: User Deactivation Decrements Count**
    - **Property 10: User Limit Enforcement**
    - **Property 12: Deactivation Audit Trail**
    - **Validates: Requirements 4.4, 5.7, 5.8, 6.2, 6.3**

  - [x] 4.11 Create SubscriptionLifecycleManager
    - Implement `SubscriptionLifecycleManager` class with methods: renewSubscription, extendSubscription
    - Implement renewSubscription: update end_date, preserve user_limit and active_user_count, create history record
    - Implement extendSubscription: validate new_user_limit >= active_user_count, update user_limit, optionally update end_date, create history record
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5, 8.6, 9.1, 9.2, 9.3, 9.4, 9.5, 9.6, 9.7_

  - [x] 4.12 Write property tests for subscription lifecycle
    - **Property 17: Renewal Requires End Date**
    - **Property 18: Renewal Updates End Date**
    - **Property 19: Renewal Preserves User Limit and Count**
    - **Property 20: Renewal Creates History Record**
    - **Property 21: Extension Limit Validation**
    - **Property 22: Extension Updates User Limit**
    - **Property 23: Extension Creates History Record**
    - **Property 24: Extension Date Update**
    - **Validates: Requirements 8.2, 8.3, 8.4, 8.5, 8.6, 9.3, 9.4, 9.5, 9.6, 9.7**

- [x] 5. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 6. Implement API gateway layer (controllers)
  - [x] 6.1 Create base controller with error handling
    - Implement `BaseController` class with methods: jsonResponse, errorResponse
    - Add exception handling for ValidationException, NotFoundException, BusinessRuleException, PDOException
    - Map exceptions to appropriate HTTP status codes (400, 404, 409, 500)
    - Format error responses with code, message, and context
    - _Requirements: 10.4, 10.5, 13.1, 13.2, 13.3, 13.4_

  - [x] 6.2 Write property tests for error response format
    - **Property 25: JSON Response Format**
    - **Property 26: HTTP Status Code Correctness**
    - **Property 27: Error Response Structure**
    - **Property 28: Validation Error Status Code**
    - **Property 29: Error Context Inclusion**
    - **Validates: Requirements 10.3, 10.4, 10.5, 13.1, 13.2, 13.3, 13.4**

  - [x] 6.3 Create ClientController
    - Implement REST endpoints: POST /api/clients, GET /api/clients, GET /api/clients/{id}, PUT /api/clients/{id}, DELETE /api/clients/{id}
    - Accept and return JSON format
    - Validate input data before processing
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 10.1, 10.2, 10.3, 10.7_

  - [x] 6.4 Create ProjectController
    - Implement REST endpoints: POST /api/projects, GET /api/clients/{clientId}/projects, GET /api/projects/{id}, PUT /api/projects/{id}, DELETE /api/projects/{id}
    - Accept and return JSON format
    - Validate input data before processing
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 10.1, 10.2, 10.3, 10.7_

  - [x] 6.5 Create ServiceController
    - Implement REST endpoints: POST /api/services, GET /api/projects/{projectId}/services, GET /api/services/{id}, PUT /api/services/{id}, DELETE /api/services/{id}, PUT /api/services/{id}/user-limit
    - Accept and return JSON format with ISO 8601 date format
    - Validate input data before processing
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 4.3, 7.3, 10.1, 10.2, 10.3, 10.7_

  - [x] 6.6 Write property test for date format consistency
    - **Property 14: Date Format Consistency**
    - **Validates: Requirements 7.3**

  - [x] 6.7 Create ValidationController
    - Implement REST endpoints: POST /api/services/{serviceId}/validate-user
    - Return validation result with success/failure and context
    - _Requirements: 5.1, 5.2, 5.3, 5.4_

  - [x] 6.8 Create UserController
    - Implement REST endpoints: POST /api/services/{serviceId}/users, DELETE /api/services/{serviceId}/users/{userId}, GET /api/services/{serviceId}/users
    - Handle user registration with transaction safety
    - _Requirements: 5.6, 6.1, 6.4_

  - [x] 6.9 Create SubscriptionController
    - Implement REST endpoints: POST /api/services/{serviceId}/renew, POST /api/services/{serviceId}/extend, GET /api/services/{serviceId}/status
    - _Requirements: 7.4, 8.1, 9.1_

  - [x] 6.10 Create ReportingController
    - Implement REST endpoints: GET /api/clients/{clientId}/utilization, GET /api/projects/{projectId}/utilization, GET /api/services/{serviceId}/utilization, GET /api/services/expiring?days={days}, GET /api/services/high-utilization?threshold={percentage}
    - _Requirements: 14.1, 14.2, 14.3, 14.5, 14.6_

  - [x] 6.11 Write property tests for reporting filters
    - **Property 33: Expiring Services Filter**
    - **Property 34: High Utilization Filter**
    - **Validates: Requirements 14.5, 14.6**

  - [x] 6.12 Create API router and CORS configuration
    - Implement router to map HTTP requests to controller methods
    - Add CORS headers for cross-origin requests
    - _Requirements: 10.1, 10.6_

  - [x] 6.13 Write property test for input validation
    - **Property 30: Input Validation**
    - **Validates: Requirements 10.7**

- [x] 7. Implement concurrency control and transaction safety
  - [x] 7.1 Add transaction wrapper to UserService.registerUser
    - Wrap validation and registration in single transaction
    - Use SELECT ... FOR UPDATE for row-level locking
    - Implement rollback on failure
    - _Requirements: 15.1, 15.2, 15.3, 15.4_

  - [x] 7.2 Write property test for concurrent user creation safety
    - **Property 35: Concurrent User Creation Safety**
    - **Validates: Requirements 15.1**

  - [x] 7.3 Write property test for transaction atomicity
    - **Property 36: Transaction Atomicity**
    - **Validates: Requirements 15.4**

  - [x] 7.4 Write property test for transaction retry
    - **Property 37: Transaction Retry**
    - **Validates: Requirements 15.5**

- [x] 8. Checkpoint - Ensure all backend tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 9. Implement frontend UI components
  - [x] 9.1 Create base UI utilities
    - Create `api.js` with fetch wrapper for API calls
    - Create `ui.js` with utility functions for DOM manipulation and message display
    - Add error handling and success message display
    - _Requirements: 12.7, 12.8_

  - [x] 9.2 Create client management interface
    - Create `client-list.js` component to display paginated list of clients
    - Create `client-form.js` component for create/edit client forms
    - Create `client-detail.js` component to view client details with projects
    - Style with Tailwind CSS
    - _Requirements: 12.1, 12.2, 12.3, 12.4_

  - [x] 9.3 Create project management interface
    - Create `project-list.js` component to display projects for selected client
    - Create `project-form.js` component for create/edit project forms
    - Create `project-detail.js` component to view project details with services
    - Style with Tailwind CSS
    - _Requirements: 12.1, 12.2, 12.3, 12.4_

  - [x] 9.4 Create service management interface
    - Create `service-list.js` component to display services for selected project
    - Create `service-form.js` component for create/edit service forms with date pickers
    - Create `service-detail.js` component to view service details with user count and status
    - Create `user-limit-badge.js` component with color coding (green < 70%, yellow 70-90%, red > 90%)
    - Create `subscription-status.js` component to display active/expired status
    - Style with Tailwind CSS
    - _Requirements: 12.1, 12.2, 12.3, 12.4, 12.5_

  - [x] 9.5 Create subscription operations interface
    - Create `renew-subscription.js` component with form to extend end date
    - Create `extend-subscription.js` component with form to increase user limit
    - Create `validation-test.js` component to test user creation validation
    - Add buttons to trigger renewal and extension operations
    - Style with Tailwind CSS
    - _Requirements: 12.6_

  - [x] 9.6 Create reporting dashboard
    - Create `utilization-chart.js` component for visual representation of utilization
    - Create `expiring-subscriptions.js` component to list subscriptions expiring soon
    - Create `high-utilization.js` component to list services near capacity
    - Style with Tailwind CSS
    - _Requirements: 14.1, 14.2, 14.3, 14.5, 14.6_

  - [x] 9.7 Implement dynamic UI updates without page reloads
    - Add event listeners for form submissions
    - Make API calls using fetch
    - Update DOM dynamically on successful API responses
    - Display success/error messages
    - _Requirements: 12.7, 12.8, 12.9_

  - [x] 9.8 Write property tests for frontend API integration
    - **Property 38: Form Submission API Integration**
    - **Property 39: API Response Feedback**
    - **Property 40: Dynamic UI Updates**
    - **Validates: Requirements 12.7, 12.8, 12.9**

- [ ] 10. Implement logging and monitoring
  - [x] 10.1 Create logging service
    - Implement `Logger` class with methods: logError, logValidationFailure, logTransaction
    - Log all validation failures with timestamp and request details
    - Log all database errors with full context
    - _Requirements: 13.5_

  - [x] 10.2 Write property test for validation logging
    - **Property 41: Validation Logging**
    - **Validates: Requirements 13.5**

- [ ] 11. Integration and deployment
  - [x] 11.1 Create database migration script
    - Create script to run SQL migrations
    - Add seed data for testing
    - _Requirements: 11.1_

  - [x] 11.2 Create API documentation
    - Document all REST endpoints with request/response examples
    - Include error codes and descriptions
    - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5_

  - [x] 11.3 Create deployment configuration
    - Create `.htaccess` for URL rewriting
    - Create `index.php` as entry point
    - Configure PHP error handling and logging
    - _Requirements: 10.1_

  - [x] 11.4 Wire all components together
    - Create dependency injection container
    - Wire repositories to services
    - Wire services to controllers
    - Wire controllers to router
    - _Requirements: All_

  - [x] 11.5 Write integration tests for complete workflows
    - Test end-to-end client → project → service → user creation flow
    - Test subscription renewal and extension workflows
    - Test validation and error handling workflows
    - _Requirements: All_

- [-] 12. Final checkpoint - Ensure all tests pass
  - Run full test suite with 1000 iterations for property tests
  - Verify all 41 correctness properties pass
  - Ensure code coverage meets 80% threshold
  - Ensure validation completes within 500ms
  - Ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties across randomized inputs
- Unit tests validate specific examples and edge cases
- All property tests use Pest with Pest Property Plugin and run minimum 100 iterations
- Concurrency tests verify race condition handling under load
