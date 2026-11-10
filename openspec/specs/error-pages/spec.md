# error-pages Specification

## Purpose

Defines how AeroPlayer responds when a request fails in production mode: the correct HTTP status with a generic page that never reveals stack traces, exception messages, file paths, hosts or credentials.

## Requirements

### Requirement: Unknown routes return 404 without internal details
With debug mode off, a request to a route that does not exist SHALL return status 404 and a response that contains no stack trace and no server file path.

#### Scenario: Unknown route
- **GIVEN** the application with debug mode off
- **WHEN** a client requests "/route-that-does-not-exist"
- **THEN** the response status is 404
- **AND** the response contains no stack trace and no server file path

### Requirement: Uncaught exceptions return 500 without internal details
With debug mode off, a request whose handling throws an uncaught exception SHALL return status 500 and a response that contains neither the exception message, nor a stack trace, nor a server file path.

#### Scenario: Exception during a request
- **GIVEN** the application with debug mode off
- **AND** a route that throws an exception with the message "boom-secret-1234"
- **WHEN** a client requests that route
- **THEN** the response status is 500
- **AND** the response does not contain "boom-secret-1234", a stack trace or a server file path

### Requirement: Database failures return 500 without credentials
With debug mode off and sessions stored in the database, a request made while the database is unreachable SHALL return status 500 and a response that reveals neither the database password, nor the database host, nor a stack trace.

#### Scenario: Unreachable database
- **GIVEN** the application with debug mode off and the database session driver
- **AND** the database connection pointing to an unreachable host with the password "db-secret-5678"
- **WHEN** a client requests "/login"
- **THEN** the response status is 500
- **AND** the response does not contain "db-secret-5678", the database host or a stack trace
