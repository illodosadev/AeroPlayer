# app-foundation Specification

## Purpose

Defines the base AeroPlayer web application every feature builds on: a server-driven single-page app whose screens are Vue page components, with an English locale, a production frontend build and a self-contained automated test suite.

## Requirements

### Requirement: English locale
The application locale SHALL be English ("en").

#### Scenario: The configured locale is English
- **GIVEN** a freshly installed application
- **WHEN** the application locale is read
- **THEN** it is "en"

### Requirement: Production frontend build
The frontend SHALL install from its lockfile and build for production with a single command that exits successfully and produces an asset manifest the server uses to link compiled assets.

#### Scenario: Production build succeeds
- **GIVEN** the project with its frontend dependencies installed from the lockfile
- **WHEN** the production frontend build is run
- **THEN** the command exits with code 0
- **AND** the asset manifest exists at "public/build/manifest.json"

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

### Requirement: Minimum PHP platform
The application SHALL be installable from its lockfile and pass its test suite on PHP 8.4.

#### Scenario: Install and test on PHP 8.4
- **GIVEN** a PHP 8.4 environment with the extensions the application requires
- **WHEN** the dependencies are installed from the lockfile and the test suite is run
- **THEN** the platform check reports no unmet requirement
- **AND** the test suite exits with code 0

### Requirement: Documented stack
The repository SHALL document the installed stack for future changes and explain how to set up, build and test the project.

#### Scenario: Stack and setup are documented
- **GIVEN** the repository after this change
- **WHEN** the project context in "openspec/config.yaml", the "Stack" section of "AGENTS.md" and the README are read
- **THEN** the context and the Stack section name Laravel, Inertia, Vue 3 with TypeScript, Tailwind, Pest, the "en" locale and the PHP 8.4 platform
- **AND** the README explains how to install dependencies, build the frontend and run the tests

### Requirement: Root URL redirects by session state
The application SHALL answer the root URL with a redirect: to the login page for a visitor without a session, and to the signed-in home page for a signed-in owner.

#### Scenario: Guest opens the root URL
- **GIVEN** a visitor without a session
- **WHEN** the visitor requests "/"
- **THEN** the response redirects to "/login"

#### Scenario: Signed-in owner opens the root URL
- **GIVEN** the owner signed in
- **WHEN** the owner requests "/"
- **THEN** the response redirects to "/dashboard"

### Requirement: Frontend type check
The frontend SHALL pass a TypeScript type check of its Vue and TypeScript sources, run with a single command.

#### Scenario: Type check passes
- **GIVEN** the project with its frontend dependencies installed from the lockfile
- **WHEN** the frontend type check is run
- **THEN** the command exits with code 0 and reports no type error
