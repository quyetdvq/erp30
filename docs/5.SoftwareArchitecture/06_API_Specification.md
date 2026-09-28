# API Specification

## Authentication API

Example:

    POST /api/login

## Organization API

    GET /api/organizations
    GET /api/organizations/{id}

## Employee API

    GET /api/employees
    GET /api/employees/{id}

## Authorization API

Example:

    POST /api/authorization/check

Request:

    {
     user_id,
     resource,
     action,
     state
    }

Response:

    {
     decision: ALLOW|DENY
    }
