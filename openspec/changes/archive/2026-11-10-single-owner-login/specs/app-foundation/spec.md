# Spec Delta

## REMOVED Requirements

### Requirement: Root page renders the Welcome page component
**Reason**: The public Welcome page is removed. Only the owner uses the app, and every page requires a session.
**Migration**: The root URL now redirects, as specified in "Root URL redirects by session state" below.

## ADDED Requirements

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
