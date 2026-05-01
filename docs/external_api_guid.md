# External API Integration Guide

This guide explains how to integrate the Subscription Management System into your external projects using the Public API (v1).

## Base Configuration

- **Production URL**: `https://subscription.sarsspl.com`
- **Auth Method**: API Key + Domain matching

---

## 1. Validate Subscription
Use this endpoint to check if a project has remaining capacity to create a new user. It checks both the **expiry date** and the **user limit**.

- **Endpoint**: `/api/v1/validate-subscription`
- **Method**: `GET` or `POST`
- **Authentication**: Required via parameters.

### Request Parameters
| Field | Type | Required | Description |
| :--- | :--- | :--- | :--- |
| `api_key` | string | Yes | Your project's unique API Key (e.g., `NbzLHFFYXgBkGLeO`) |
| `domain` | string | Yes | Your project's registered domain (e.g., `https://project.sarsspl.com/`) |

### Success Response (`200 OK`)
```json
{
  "status": "success",
  "allowed": true,
  "message": "User creation allowed.",
  "data": {
    "service_id": 2,
    "type": "Standard",
    "limit": 100,
    "current": 45,
    "expiry": "2027-05-01"
  }
}
