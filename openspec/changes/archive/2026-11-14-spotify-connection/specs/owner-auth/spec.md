# Spec Delta

## MODIFIED Requirements

### Requirement: Registration, password reset, verification, settings and two-factor are removed
The system SHALL NOT offer sign-up, password reset, email verification, profile, password or appearance settings, or two-factor authentication. The routes for these features SHALL return status 404, and no route whose URI or name starts with "two-factor" SHALL be registered. "/settings" is no longer one of them: it leads to the Spotify settings.

#### Scenario: Removed routes return 404
- **WHEN** a visitor sends GET or POST to "/register" or "/forgot-password", GET to "/reset-password/any-token" or POST to "/reset-password", or the signed-in owner sends a request to "/verify-email", "/email/verification-notification", "/confirm-password", "/settings/profile", "/settings/password" or "/settings/appearance"
- **THEN** the response status is 404

#### Scenario: Login page shows no sign-up or password reset link
- **GIVEN** a visitor without a session
- **WHEN** the visitor opens "/login" in a browser
- **THEN** the page shows none of the texts "Register", "Sign up" or "Forgot password?"

#### Scenario: User menu links lead to existing pages only
- **GIVEN** the owner signed in on "/dashboard" in a browser
- **WHEN** the owner opens the user menu and follows each of its links except "Log out"
- **THEN** none of those pages responds with status 404
- **AND** the menu shows a "Settings" link to "/settings/spotify"
- **AND** the menu has no link to "/settings/profile", "/settings/password" or "/settings/appearance"

#### Scenario: Registration attempt creates no user
- **GIVEN** the users table is empty
- **WHEN** a visitor sends a POST to "/register" with a name, email and valid password
- **THEN** the response status is 404
- **AND** the users table stays empty

#### Scenario: No two-factor routes
- **WHEN** the registered routes are listed
- **THEN** no route URI starts with "two-factor", "user/two-factor" or "settings/two-factor"
- **AND** no route name starts with "two-factor"
