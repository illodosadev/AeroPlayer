## ADDED Requirements

### Requirement: Aero slider
The Aero slider SHALL let its value be changed with the arrow, Home and End keys and with the pointer, SHALL expose its current value to assistive technology, SHALL keep its value within its minimum and maximum, and SHALL ignore input when disabled.

#### Scenario: The keyboard moves the slider one step
- **GIVEN** the owner on "/styleguide" and the "Volume" slider of the "Sliders" section at a value between its limits
- **WHEN** the slider receives focus and the right arrow key is pressed
- **THEN** its value increases by one step
- **AND** its accessible value (the input value or aria-valuenow) shows the new value

#### Scenario: The pointer moves the slider and stops at the end
- **GIVEN** the "Seek" slider of the "Sliders" section at a value between its limits
- **WHEN** the thumb is dragged with the pointer to a point past the end of the track
- **THEN** its value is the maximum
- **AND** it is not above the maximum

#### Scenario: The slider stays within its limits
- **GIVEN** the "Volume" slider of the "Sliders" section at its maximum
- **WHEN** the right arrow key is pressed, then End, then Home
- **THEN** after the right arrow its value is still the maximum
- **AND** after End it is the maximum
- **AND** after Home it is the minimum

#### Scenario: A disabled slider ignores input
- **GIVEN** the disabled slider of the "Sliders" section
- **WHEN** its thumb is dragged with the pointer and the page is walked with Tab
- **THEN** its value does not change
- **AND** it never receives focus

### Requirement: Aero cover with fallback
The Aero cover SHALL show the song's image with a non-empty text alternative when the image loads, and SHALL show a generic Aero cover, hidden from assistive technology, when there is no image or the image fails to load. The cover SHALL keep the same size in every case.

#### Scenario: A song without a cover
- **GIVEN** the owner on "/styleguide"
- **WHEN** the "Cover" section shows the sample without an image URL
- **THEN** that sample shows the element with data-aero="cover-fallback"
- **AND** that sample has no "<img>" element
- **AND** the fallback element has aria-hidden="true"

#### Scenario: The cover image fails to load
- **GIVEN** the "Cover" section with a sample whose image URL answers 404
- **WHEN** the page has finished loading
- **THEN** that sample shows the element with data-aero="cover-fallback"
- **AND** that sample shows no "<img>" whose naturalWidth is 0

#### Scenario: A cover with an image
- **GIVEN** the "Cover" section with a sample whose image is valid
- **WHEN** the image has loaded
- **THEN** the sample shows an "<img>" with a non-empty alt text
- **AND** that sample has no element with data-aero="cover-fallback"

#### Scenario: Every cover keeps its size
- **GIVEN** the three samples of the "Cover" section, all set to the same size
- **WHEN** the page has finished loading
- **THEN** the three samples have the same width and height

### Requirement: Aero player bar controls
The Aero player bar SHALL sit inside an Aero glass panel and SHALL show a cover, a title, an artist, the controls "Previous", "Play" and "Next", a "Seek" slider and a "Volume" slider. "Play" SHALL be a larger glossy orb than "Previous" and "Next". Every interactive control of the bar SHALL have a touch area of at least 44x44 px.

#### Scenario: The bar has the basic controls
- **GIVEN** the owner signed in on "/styleguide"
- **WHEN** the owner looks at the "Player bar" section
- **THEN** the bar shows a cover, a title, an artist and the controls with the accessible names "Previous", "Play", "Next", "Seek" and "Volume"
- **AND** the bar is inside an element with data-aero="glass-panel"
- **AND** "Previous", "Play" and "Next" are orb-shaped Aero buttons, and "Play" is wider than "Previous" and "Next"

### Requirement: Player bar play and pause
Pressing "Play" on the player bar SHALL turn the control into "Pause" with a pause icon, and pressing "Pause" SHALL turn it back. The bar SHALL NOT create any audio or video element.

#### Scenario: Play and pause change the control without sound
- **GIVEN** the player bar of the "Player bar" section
- **WHEN** the "Play" control is pressed
- **THEN** the control's accessible name becomes "Pause" and it shows the pause icon
- **AND** the page has no "<audio>" or "<video>" element
- **AND** pressing "Pause" turns the control back into "Play"

### Requirement: Player bar time display
The player bar SHALL show the elapsed time and the total time of the song as "m:ss" ("h:mm:ss" from one hour), and the "Seek" slider SHALL expose the same position to assistive technology as "<elapsed> of <total>".

#### Scenario: The bar shows the times
- **GIVEN** the player bar of the "Player bar" section at 10 seconds of a 180-second song
- **WHEN** the owner looks at the bar
- **THEN** the bar shows "0:10" and "3:00"
- **AND** the "Seek" slider has aria-valuetext "0:10 of 3:00"

#### Scenario: Seeking updates the elapsed time
- **GIVEN** the player bar at 10 seconds of a 180-second song
- **WHEN** the "Seek" slider receives focus and the right arrow key is pressed
- **THEN** the bar shows "0:11"

#### Scenario: A song of an hour or more
- **GIVEN** the player bar at 0 seconds of a 3725-second song
- **WHEN** the owner looks at the bar
- **THEN** the bar shows "0:00" and "1:02:05"

### Requirement: Player bar text layout
The player bar SHALL keep its height when the title or the artist is empty, without showing placeholder text, and SHALL show a long title on one line cut with an ellipsis, with the full title in its "title" attribute.

#### Scenario: Empty title and artist do not move the bar
- **GIVEN** the player bar of the "Player bar" section with the title "Song A" and the artist "Artist A"
- **WHEN** the sample switches to an empty title and an empty artist
- **THEN** the bar's height (getBoundingClientRect().height) does not change
- **AND** no text appears in place of the title or the artist

#### Scenario: A long title on a phone
- **GIVEN** a 375x667 px viewport and the player bar with a 120-character title
- **WHEN** "/styleguide" is opened
- **THEN** document.documentElement.scrollWidth is at most 375
- **AND** every control of the bar is visible within the viewport width
- **AND** every interactive control of the bar has a touch area of at least 44x44 px
- **AND** the title is shown on one line with text-overflow "ellipsis" and white-space "nowrap", and its "title" attribute holds the full title

### Requirement: Player bar disabled controls
Each of "Previous", "Play", "Next" and "Seek" on the player bar SHALL be able to be disabled on its own. A disabled control SHALL ignore clicks and SHALL NOT receive keyboard focus.

#### Scenario: Disabled controls cannot be used
- **GIVEN** the "Player bar" section's sample bar with "Previous", "Play", "Next" and "Seek" disabled
- **WHEN** the buttons are clicked, the "Seek" thumb is dragged with the pointer and the page is walked with Tab
- **THEN** each of them has the "disabled" attribute
- **AND** none of them receives focus
- **AND** the "Seek" value does not change

### Requirement: Player bar notice area
The player bar SHALL be able to show a notice inside the bar with a message, an optional action button and an optional link. Assistive technology SHALL announce the message politely when it appears or changes, without announcing the actions as part of it.

#### Scenario: A notice with a button and a link
- **GIVEN** the "Player bar" section's sample bar with a notice
- **WHEN** the owner looks at it
- **THEN** the bar shows the message "Playing on Kitchen Speaker", an Aero button "Play here" and a link "Reconnect Spotify" inside the bar
- **AND** the message is inside an element with role="status"
- **AND** "Play here" and "Reconnect Spotify" are outside that element
- **AND** "Play here" and "Reconnect Spotify" are reachable with Tab

### Requirement: Player bar extra controls
The player bar SHALL accept extra buttons from other features and show them among its controls, with the same touch area and focus rules as its own controls.

#### Scenario: An extra toggle button in the bar
- **GIVEN** the "Player bar" section's sample bar with an extra toggle button "Extra" whose aria-pressed is "false"
- **WHEN** the "Extra" button is clicked
- **THEN** it is inside the bar's panel
- **AND** its aria-pressed becomes "true"
- **AND** its touch area is at least 44x44 px

## MODIFIED Requirements

### Requirement: Aero style skill
The repository SHALL contain a project Claude skill at ".claude/skills/frutiger-aero/SKILL.md" whose description says to use it when creating or changing the app's interface. The skill, meaning SKILL.md and the reference files in its folder, SHALL cover the four discovery traits, name each base component and each player control with its file path, list exactly the design tokens the app declares, and state the accessibility rules.

#### Scenario: The skill file exists with its frontmatter
- **GIVEN** the repository after this change
- **WHEN** ".claude/skills/frutiger-aero/SKILL.md" is opened
- **THEN** the file exists and its frontmatter has "name" and "description"
- **AND** the description says it is used when creating or changing the app's interface

#### Scenario: The skill covers traits, components and accessibility
- **GIVEN** the skill "frutiger-aero"
- **WHEN** SKILL.md and the reference files in its folder are read
- **THEN** it has a section for each trait: glossy and glass, nature and sky, classic media players, and skeuomorphism
- **AND** it names AeroButton, AeroGlassPanel, AeroInput, AeroCheckbox, AeroBackground, AeroSlider, AeroCover and AeroPlayerBar with the path of their files
- **AND** its classic media players section names AeroPlayerBar and AeroSlider and links to the reference that describes them
- **AND** it states the rules for visible focus, reduced motion, the no-blur fallback, minimum contrast and minimum touch target

#### Scenario: The skill lists exactly the declared tokens
- **GIVEN** every stylesheet of the app that declares "--aero-*" custom properties
- **WHEN** the token names declared there are compared with the token names in SKILL.md and its reference files
- **THEN** every declared token appears in the skill
- **AND** the skill names no "--aero-*" token that is not declared

### Requirement: Styleguide page
The application SHALL offer a "/styleguide" page, to the signed-in owner only, that shows every base Aero component and its states over the Aero background, in the sections "Buttons", "Glass panel", "Inputs", "Checkbox", "Sliders", "Cover" and "Player bar".

#### Scenario: The owner opens the styleguide
- **GIVEN** the owner signed in
- **WHEN** the owner requests "/styleguide"
- **THEN** the response status is 200
- **AND** the Inertia response names the component "Styleguide"
- **AND** the page shows the sections "Buttons", "Glass panel", "Inputs", "Checkbox", "Sliders", "Cover" and "Player bar" over the Aero background

#### Scenario: A visitor without a session cannot see the styleguide
- **GIVEN** a visitor without a session
- **WHEN** the visitor requests "/styleguide"
- **THEN** the response redirects to "/login"

### Requirement: Reduced motion
When the browser asks for reduced motion, Aero pages (currently "/login" and "/styleguide") SHALL run no animation and SHALL keep every transition at 0.01 s or less.

#### Scenario: No animation with reduced motion
- **GIVEN** the browser emulates prefers-reduced-motion: reduce
- **WHEN** "/login" and "/styleguide" are opened and the pointer moves over the buttons
- **THEN** no element, including the background layers, has an animation-name other than "none" or an animation duration above 0
- **AND** every transition lasts 0.01 s or less

#### Scenario: No animation on the player controls with reduced motion
- **GIVEN** the browser emulates prefers-reduced-motion: reduce
- **WHEN** "/styleguide" is opened, "Play" is pressed on the player bar and the sliders are moved
- **THEN** no element of the "Sliders", "Cover" and "Player bar" sections has an animation-name other than "none"
- **AND** every transition there lasts 0.01 s or less

### Requirement: Asset provenance
Every visual asset file of the Aero look (backgrounds, icons, fonts and sample images) SHALL have an entry in the provenance log with its origin (a URL or "hand-made") and the date it was added.

#### Scenario: Every asset is logged
- **GIVEN** the asset folders for backgrounds, icons, fonts and sample images
- **WHEN** their files are compared with the provenance log
- **THEN** each file has an entry with its origin and the date it was added
