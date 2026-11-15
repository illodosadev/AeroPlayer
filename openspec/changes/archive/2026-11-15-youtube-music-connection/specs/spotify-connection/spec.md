# Spec Delta

## ADDED Requirements

### Requirement: The Spotify settings page links to the YouTube Music settings
"/settings/spotify" SHALL show the settings link bar with "Spotify" and "YouTube Music", with "Spotify" marked as the current page, and "YouTube Music" SHALL lead to "/settings/youtube-music". "GET /settings" SHALL keep redirecting to "/settings/spotify".

#### Scenario: Reaching the YouTube Music settings
- **GIVEN** the owner on "/settings/spotify"
- **WHEN** the owner presses "YouTube Music" in the settings link bar
- **THEN** "/settings/youtube-music" opens, with the same link bar and "Spotify" to go back

#### Scenario: The current page is marked
- **GIVEN** the owner signed in
- **WHEN** the owner opens "/settings/spotify"
- **THEN** the "Spotify" link in the settings link bar is marked as the current page with aria-current="page"
- **AND** the "YouTube Music" link is not
