# Spec Delta

## ADDED Requirements

### Requirement: The settings page shows a configuration error
When the connection carries the configuration-error mark that a refused renewal leaves, "/settings/youtube-music" SHALL show "YouTube Music is not configured correctly." next to the connection state, without sending anything to Google. Without the mark it SHALL not show that text.

#### Scenario: A renewal was refused for the client
- **GIVEN** YouTube Music connected with the channel name "Alice Music" and the configuration-error mark
- **WHEN** the owner opens "/settings/youtube-music"
- **THEN** the page shows "YouTube Music connected", "Alice Music" and "YouTube Music is not configured correctly."
- **AND** no request is sent to Google

#### Scenario: No configuration error
- **GIVEN** YouTube Music connected without the configuration-error mark
- **WHEN** the owner opens "/settings/youtube-music"
- **THEN** the page does not show "YouTube Music is not configured correctly."
