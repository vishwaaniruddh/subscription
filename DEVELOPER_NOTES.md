# Developer Notes: Subscription Management Module

## Audit & Activity Logging Requirement
**Critical Requirement:** Every administrative action, data modification, or system request MUST be recorded for audit transparency and accountability.

### Implementation Details
- **Database Table:** `activity_log`
- **Backend Service:** `App\Repositories\ActivityLogRepository`
- **Automatic Capturing:** The following controllers have been updated to log all CRUD operations:
    - `ClientController`: Create, Update, Delete.
    - `ProjectController`: Create, Update, Delete.
    - `ServiceController`: Create, Update, Delete.
    - `SubscriptionController`: Renewals, Extensions.
    - `UserController`: Registration, Deactivation.

### Logging Schema
Logs capture the following data points:
1. `entity_type`: The type of object affected (client, project, service, user, subscription).
2. `entity_id`: The specific record ID.
3. `action`: The nature of the operation (e.g., `created`, `updated`, `deleted`).
4. `description`: A human-readable summary of the event.
5. `old_data` / `new_data`: JSON snapshots of the record state before and after modification (essential for debugging and forensic analysis).
6. `ip_address`: The IP of the requester.
7. `created_at`: Precise timestamp of the event.

### UI Visibility
A dedicated "Activity Log" section is available in the administrative dashboard to monitor system events in real-time.

> [!IMPORTANT]
> When adding new API endpoints or business logic, ensure you inject the `ActivityLogRepository` and call `$this->activityLog->log(...)` upon successful execution.
