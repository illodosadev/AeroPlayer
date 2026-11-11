# Spec Delta

## MODIFIED Requirements

### Requirement: Self-contained automated test suite
The unit and feature test suites SHALL pass without any external database server, network service or built frontend assets. The browser test suite MAY require a production frontend build and an installed headless browser, and SHALL need no external database server or network service.

#### Scenario: Test suite passes without a database server or built assets
- **GIVEN** the project with its development dependencies installed
- **AND** no MySQL server running and no "public/build" folder
- **WHEN** the unit and feature test suites are run
- **THEN** the command exits with code 0

#### Scenario: Browser tests pass with a build and no database server
- **GIVEN** the project with its development dependencies and headless browser installed and the frontend built for production
- **AND** no MySQL server running
- **WHEN** the full test suite, browser tests included, is run
- **THEN** the command exits with code 0

## ADDED Requirements

### Requirement: Frontend type check
The frontend SHALL pass a TypeScript type check of its Vue and TypeScript sources, run with a single command.

#### Scenario: Type check passes
- **GIVEN** the project with its frontend dependencies installed from the lockfile
- **WHEN** the frontend type check is run
- **THEN** the command exits with code 0 and reports no type error
