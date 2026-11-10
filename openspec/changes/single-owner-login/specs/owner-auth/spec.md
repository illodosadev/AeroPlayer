# Spec Delta

## Purpose

Lets the single owner of AeroPlayer sign in with email and password to reach the player, and keeps the account creatable only from the server console, with no other way in.

## ADDED Requirements

### Requirement: Owner signs in with email and password
The system SHALL let a visitor sign in with the email and password of the owner account, and SHALL treat the email as case-insensitive. A successful login SHALL regenerate the session identifier and redirect to the signed-in home page.

#### Scenario: Correct credentials sign in
- **GIVEN** an owner account for "owner@example.com" with password "Correct-Horse-42"
- **AND** a visitor without a session
- **WHEN** the visitor submits "owner@example.com" and "Correct-Horse-42" on the login page
- **THEN** the visitor is signed in as the owner
- **AND** the visitor is redirected to "/dashboard"
- **AND** the session identifier differs from the one before the login

#### Scenario: Email case is ignored
- **GIVEN** an owner account for "owner@example.com" with password "Correct-Horse-42"
- **WHEN** the visitor submits "Owner@Example.com" and "Correct-Horse-42"
- **THEN** the visitor is signed in and redirected to "/dashboard"

### Requirement: Failed login reveals nothing about accounts
A failed login SHALL keep the visitor signed out, redirect to the login page and report one generic error on the email field, whether the email is unknown, the password is wrong, or no owner account exists. It SHALL NOT return status 500.

#### Scenario: Wrong password or unknown email
- **GIVEN** an owner account for "owner@example.com" with password "Correct-Horse-42"
- **WHEN** the visitor submits "owner@example.com" with "wrong", or "nobody@example.com" with "Correct-Horse-42"
- **THEN** the visitor is not signed in
- **AND** the visitor is redirected to "/login"
- **AND** the session carries the error "These credentials do not match our records." for the field "email"

#### Scenario: No owner account exists yet
- **GIVEN** the users table is empty
- **WHEN** a visitor submits "owner@example.com" and "Correct-Horse-42" on the login page
- **THEN** the visitor is not signed in
- **AND** the response status is not 500

#### Scenario: Incomplete login form
- **GIVEN** a visitor without a session
- **WHEN** the visitor submits the login form with the email empty
- **THEN** the visitor is not signed in
- **AND** the session carries the error "The email field is required." for the field "email"

### Requirement: Login attempts are rate limited
The system SHALL reject further login attempts for an email and IP pair after five failed attempts within one minute, even when the password is correct. A blocked attempt SHALL redirect to the login page with an error on the email field. The limit SHALL lift once the minute has passed.

#### Scenario: Sixth attempt within the minute is blocked
- **GIVEN** five failed login attempts for "owner@example.com" from the same IP within the last minute
- **WHEN** a sixth attempt with the correct password is made within that minute
- **THEN** the visitor is not signed in
- **AND** the visitor is redirected to "/login"
- **AND** the session carries a "Too many login attempts" error for the field "email"

#### Scenario: Limit lifts after one minute
- **GIVEN** the login for "owner@example.com" blocked after five failed attempts
- **WHEN** 61 seconds pass and the correct credentials are submitted
- **THEN** the visitor is signed in and redirected to "/dashboard"

### Requirement: Remember me keeps the owner signed in
The login form SHALL offer a "Remember me" option that is off by default. When it is checked, the response SHALL set a remember-me cookie that keeps the owner signed in after the session expires.

#### Scenario: Remember me checked
- **GIVEN** a visitor without a session
- **WHEN** the owner signs in with the correct credentials and "remember" set to true
- **THEN** the response sets a "remember_web_*" cookie

#### Scenario: Remember me not checked
- **GIVEN** a visitor without a session
- **WHEN** the owner signs in with the correct credentials and "remember" not sent
- **THEN** the response sets no "remember_web_*" cookie

#### Scenario: Remember cookie works after session expiry
- **GIVEN** the owner signed in with "remember" set to true
- **AND** the session has expired but the "remember_web_*" cookie is kept
- **WHEN** the owner opens "/dashboard"
- **THEN** the response status is 200 and the owner stays signed in

### Requirement: Protected pages require a session, and signed-in owners skip the login page
Every page of the player SHALL require a signed-in owner. A visitor without a session SHALL be redirected to "/login". An owner with a session who opens "/login" SHALL be redirected to "/dashboard".

#### Scenario: Protected page without session
- **GIVEN** a visitor without a session
- **WHEN** the visitor opens "/dashboard"
- **THEN** the visitor is redirected to "/login"

#### Scenario: Login page while signed in
- **GIVEN** the owner signed in
- **WHEN** the owner opens "/login"
- **THEN** the owner is redirected to "/dashboard"

#### Scenario: Login page offers no password reset
- **GIVEN** a visitor without a session
- **WHEN** the visitor opens "/login"
- **THEN** the page prop "canResetPassword" is false

### Requirement: Logout ends the session
The system SHALL end the owner session on logout. Logout SHALL accept only POST requests with a valid CSRF token, and SHALL redirect to "/login". After logout, going back in the browser SHALL NOT show any page or data that required the session.

#### Scenario: Logout ends the session
- **GIVEN** the owner signed in
- **WHEN** the owner sends a POST to "/logout"
- **THEN** the session ends
- **AND** the owner is redirected to "/login"
- **AND** opening "/dashboard" afterwards redirects to "/login"

#### Scenario: Logout with GET or without CSRF token
- **GIVEN** the owner signed in and CSRF protection active
- **WHEN** the owner sends a GET to "/logout", or a POST without a valid CSRF token
- **THEN** the response status is 405 for GET and 419 for the POST
- **AND** the owner stays signed in

#### Scenario: Going back after logout shows no protected page
- **GIVEN** the owner signed in and viewing "/dashboard"
- **WHEN** the owner clicks "Log out" and then the browser back button
- **THEN** the address ends in "/login"
- **AND** the content of "/dashboard" is not shown

#### Scenario: Logout on one device keeps another device signed in
- **GIVEN** the session driver is "database"
- **AND** the owner signed in on device A and on device B without "Remember me"
- **WHEN** the owner logs out on device A
- **THEN** opening "/dashboard" on device B still responds with status 200

### Requirement: Registration, password reset, verification, settings and two-factor are removed
The system SHALL NOT offer sign-up, password reset, email verification, profile, password or appearance settings, or two-factor authentication. The routes for these features SHALL return status 404, and no route whose URI or name starts with "two-factor" SHALL be registered.

#### Scenario: Removed routes return 404
- **WHEN** a visitor sends GET or POST to "/register" or "/forgot-password", GET to "/reset-password/any-token" or POST to "/reset-password", or the signed-in owner sends a request to "/verify-email", "/email/verification-notification", "/confirm-password", "/settings", "/settings/profile", "/settings/password" or "/settings/appearance"
- **THEN** the response status is 404

#### Scenario: Login page shows no sign-up or password reset link
- **GIVEN** a visitor without a session
- **WHEN** the visitor opens "/login" in a browser
- **THEN** the page shows none of the texts "Register", "Sign up" or "Forgot password?"

#### Scenario: User menu links lead to existing pages only
- **GIVEN** the owner signed in on "/dashboard" in a browser
- **WHEN** the owner opens the user menu and follows each of its links except "Log out"
- **THEN** none of those pages responds with status 404
- **AND** the menu shows no "Settings" text and no link to "/settings" or to a URL starting with "/settings/"

#### Scenario: Registration attempt creates no user
- **GIVEN** the users table is empty
- **WHEN** a visitor sends a POST to "/register" with a name, email and valid password
- **THEN** the response status is 404
- **AND** the users table stays empty

#### Scenario: No two-factor routes
- **WHEN** the registered routes are listed
- **THEN** no route URI starts with "two-factor", "user/two-factor" or "settings/two-factor"
- **AND** no route name starts with "two-factor"

### Requirement: Owner account is created from the console
The system SHALL provide the console command "app:create-owner", which SHALL be the only way to create or change the owner account. It SHALL refuse to run in non-interactive mode and SHALL take no email or password as arguments.

#### Scenario: Create the owner interactively
- **GIVEN** the users table is empty
- **WHEN** the command runs interactively and the answers are "owner@example.com", "Correct-Horse-42" and the confirmation "Correct-Horse-42"
- **THEN** the command exits with code 0 and prints "Owner account created for owner@example.com."
- **AND** the users table has exactly one row with the email "owner@example.com"
- **AND** the stored password is not the plain text "Correct-Horse-42"
- **AND** the owner can sign in at "/login" with those credentials

#### Scenario: Email is stored in lowercase
- **GIVEN** the users table is empty
- **WHEN** the command runs interactively and the email answer is "Owner@Example.com"
- **THEN** the users table contains the email "owner@example.com"

#### Scenario: Invalid answer is asked again
- **GIVEN** the users table is empty
- **WHEN** the command runs interactively and an invalid answer is given
- **THEN** the command prints the validation error for that answer and asks the same question again
- **AND** once a valid answer is given, the command exits with code 0 and the users table has exactly one row

#### Scenario: Password rules
- **WHEN** a password is given that is shorter than 12 characters, lacks uppercase or lowercase letters, lacks a number, lacks a symbol, or whose confirmation does not match
- **THEN** the command prints the matching error from the English validation messages and asks again

#### Scenario: Non-interactive run does nothing
- **GIVEN** the users table is empty, or it already holds the owner account
- **WHEN** the command runs with "--no-interaction"
- **THEN** the command exits with a non-zero code and prints "This command needs an interactive terminal."
- **AND** the users table does not change

### Requirement: Replacing the owner account ends every other session
When an owner account already exists, the command SHALL ask for confirmation before replacing its email and password. It SHALL keep a single account. Replacing the password SHALL end every other session of that account.

#### Scenario: Confirmed replacement
- **GIVEN** an owner account for "owner@example.com" with password "Correct-Horse-42"
- **AND** the owner is signed in on another device, with a "remember_web_*" cookie, and the session driver is "database"
- **WHEN** the command runs interactively, the replacement question is answered "yes", and the answers are "owner@example.com" and "New-Horse-77" confirmed
- **THEN** the command exits with code 0 and prints "Owner account updated for owner@example.com."
- **AND** the users table still has exactly one row
- **AND** signing in with "Correct-Horse-42" fails and with "New-Horse-77" succeeds
- **AND** on the other device, opening "/dashboard" redirects to "/login"

#### Scenario: Declined replacement
- **GIVEN** an owner account for "owner@example.com" with password "Correct-Horse-42"
- **WHEN** the command runs interactively and the replacement question is answered "no"
- **THEN** the command exits with a non-zero code and prints "Nothing changed."
- **AND** signing in with "Correct-Horse-42" still works
