# Spec Delta

## Purpose

Defines AeroPlayer's Frutiger Aero visual system: shared design tokens and style rules, the base glossy and glass components, the styleguide page, the Aero login, and the accessibility, light-mode and mobile guarantees every Aero screen keeps.

## ADDED Requirements

### Requirement: Aero style skill
The repository SHALL contain a project Claude skill at ".claude/skills/frutiger-aero/SKILL.md" whose description says to use it when creating or changing the app's interface. The skill, meaning SKILL.md and the reference files in its folder, SHALL cover the four discovery traits, name each base component with its file path, list exactly the design tokens the app declares, and state the accessibility rules.

#### Scenario: The skill file exists with its frontmatter
- **GIVEN** the repository after this change
- **WHEN** ".claude/skills/frutiger-aero/SKILL.md" is opened
- **THEN** the file exists and its frontmatter has "name" and "description"
- **AND** the description says it is used when creating or changing the app's interface

#### Scenario: The skill covers traits, components and accessibility
- **GIVEN** the skill "frutiger-aero"
- **WHEN** SKILL.md and the reference files in its folder are read
- **THEN** it has a section for each trait: glossy and glass, nature and sky, classic media players, and skeuomorphism
- **AND** it names AeroButton, AeroGlassPanel, AeroInput, AeroCheckbox and AeroBackground with the path of their files
- **AND** it states the rules for visible focus, reduced motion, the no-blur fallback, minimum contrast and minimum touch target

#### Scenario: The skill lists exactly the declared tokens
- **GIVEN** every stylesheet of the app that declares "--aero-*" custom properties
- **WHEN** the token names declared there are compared with the token names in SKILL.md and its reference files
- **THEN** every declared token appears in the skill
- **AND** the skill names no "--aero-*" token that is not declared

### Requirement: Themable design tokens
Colours, gradients, shadows and the background of the Aero look SHALL come from "--aero-*" custom properties that belong to a named theme, so that another theme can replace them without changing components. The default theme SHALL be "sky", and every page SHALL mark its root element with the active theme.

#### Scenario: Pages carry the default theme
- **GIVEN** a visitor without a session
- **WHEN** the visitor opens "/login"
- **THEN** the "<html>" element has the attribute data-theme="sky"

#### Scenario: A theme token reaches the components
- **GIVEN** the owner signed in on "/styleguide"
- **WHEN** the theme's glass panel background token is overridden on "<html>" with another colour
- **THEN** the computed background colour of the glass panel changes to that colour

### Requirement: Styleguide page
The application SHALL offer a "/styleguide" page, to the signed-in owner only, that shows every base Aero component and its states over the Aero background, in the sections "Buttons", "Glass panel", "Inputs" and "Checkbox".

#### Scenario: The owner opens the styleguide
- **GIVEN** the owner signed in
- **WHEN** the owner requests "/styleguide"
- **THEN** the response status is 200
- **AND** the Inertia response names the component "Styleguide"
- **AND** the page shows the sections "Buttons", "Glass panel", "Inputs" and "Checkbox" over the Aero background

#### Scenario: A visitor without a session cannot see the styleguide
- **GIVEN** a visitor without a session
- **WHEN** the visitor requests "/styleguide"
- **THEN** the response redirects to "/login"

### Requirement: Glossy button states
The glossy button SHALL exist as a rectangle and as a round orb, and each SHALL look different in the normal, hover, pressed and disabled states. An enabled button SHALL respond to a click.

#### Scenario: The four states side by side
- **GIVEN** the owner on "/styleguide"
- **WHEN** the owner looks at the "Buttons" section
- **THEN** the rectangle and the orb are shown in the states "Normal", "Hover", "Pressed" and "Disabled", each with its label
- **AND** the computed background-image or box-shadow of each state differs from the other three

#### Scenario: Real hover and press change the look
- **GIVEN** the interactive button of the "Buttons" section
- **WHEN** the pointer moves over it and then holds the mouse button down
- **THEN** the computed style under the pointer differs from the normal state
- **AND** the computed style while held down differs from the hover state

#### Scenario: Pressing and releasing outside does not stick
- **GIVEN** the interactive button of the "Buttons" section
- **WHEN** the mouse button is pressed on it, the pointer leaves it and the mouse button is released outside
- **THEN** the computed style of the button is the same as in the normal state

#### Scenario: The enabled button responds to a click
- **GIVEN** the "Buttons" section showing "Clicks: 0"
- **WHEN** the interactive button is clicked
- **THEN** the counter shows "Clicks: 1"

### Requirement: Disabled button cannot be used
A disabled glossy button SHALL ignore clicks and SHALL NOT receive keyboard focus.

#### Scenario: The disabled button ignores clicks and Tab
- **GIVEN** the "Buttons" section showing "Disabled clicks: 0" for the button in the "Disabled" state
- **WHEN** the disabled button is clicked and the page is walked with Tab
- **THEN** the button has the "disabled" attribute
- **AND** it never receives focus
- **AND** the counter still shows "Disabled clicks: 0"

### Requirement: Glass panel lets the background through
Where the browser supports backdrop blur, the glass panel SHALL blur what lies behind it and SHALL have a translucent background.

#### Scenario: The panel is translucent and blurred
- **GIVEN** a browser with backdrop-filter support
- **WHEN** "/styleguide" is opened
- **THEN** the panel of the "Glass panel" section has a computed backdrop-filter other than "none"
- **AND** the alpha of its computed background colour is below 1

### Requirement: No-blur fallback
Where the browser does not support backdrop blur, or the fallback is forced with the class "aero-no-blur" on "<html>", every glass panel SHALL use an almost opaque background with readable text.

#### Scenario: The fallback panel is almost opaque and readable
- **GIVEN** the class "aero-no-blur" on "<html>"
- **WHEN** "/styleguide" is opened
- **THEN** the computed backdrop-filter of every panel is "none"
- **AND** the alpha of every panel's computed background colour is at least 0.85
- **AND** the panel text has a contrast ratio of at least 4.5:1 with the panel colour

### Requirement: Aero text input
The Aero text input SHALL accept typing and SHALL expose its error message to assistive technology.

#### Scenario: Typing and an error message
- **GIVEN** the "Inputs" section with an "Email" input and an input showing the sample error "This field is required."
- **WHEN** "owner@example.com" is typed into "Email"
- **THEN** the value of "Email" is "owner@example.com"
- **AND** the input with the error has aria-invalid="true" and its message is linked with aria-describedby

### Requirement: Aero checkbox
The Aero checkbox SHALL toggle with the mouse, with its label and with the Space key, SHALL look different when checked, and SHALL have a clickable area of at least 44x44 px including its label.

#### Scenario: Mouse and keyboard toggle the checkbox
- **GIVEN** the "Checkbox" section with an unchecked and a checked checkbox
- **WHEN** the unchecked checkbox is clicked, then focused with Tab and Space is pressed
- **THEN** after the click it is checked
- **AND** after Space it is unchecked again
- **AND** the checked and unchecked states have different computed styles

#### Scenario: The label toggles and the area is large enough
- **GIVEN** the checkbox of the "Checkbox" section with its label
- **WHEN** its clickable area is measured and its label is clicked
- **THEN** the area is at least 44x44 px
- **AND** clicking the label changes the checked state

### Requirement: Aero login page
The login page SHALL be built from the Aero background, a glass panel, Aero inputs for "Email" and "Password", an unchecked Aero checkbox "Remember me" and an Aero button "Log in", with no shadcn-vue component (nothing from "@/components/ui"). The login behaviour of "owner-auth" SHALL stay unchanged.

#### Scenario: The login looks Aero
- **GIVEN** a visitor without a session
- **WHEN** the visitor opens "/login"
- **THEN** the page holds, located by their data-aero hooks, the Aero background, a glass panel, Aero inputs "Email" and "Password", an unchecked Aero checkbox "Remember me" and an Aero button "Log in"
- **AND** the page has no element with the attribute "data-slot"
- **AND** the login page source imports nothing from "@/components/ui"

#### Scenario: The credentials error is readable
- **GIVEN** the owner account exists, a visitor without a session and the class "aero-no-blur" on "<html>"
- **WHEN** a wrong password is submitted on "/login"
- **THEN** "These credentials do not match our records." is shown inside the glass panel
- **AND** its text has a contrast ratio of at least 4.5:1 with the panel colour

#### Scenario: The Aero login still signs in
- **GIVEN** the owner account exists and a visitor without a session
- **WHEN** the visitor fills "Email" and "Password" with the right credentials and presses "Log in"
- **THEN** the visitor is signed in and leaves "/login"

### Requirement: Visible keyboard focus
Every enabled interactive control on Aero pages (currently "/login" and "/styleguide") SHALL be reachable with Tab and SHALL show a focus indicator of at least 2 px that it does not show without focus.

#### Scenario: Focus is visible on every control
- **GIVEN** the owner on "/styleguide", or a visitor on "/login"
- **WHEN** every control is walked with Tab
- **THEN** each focused control shows an outline or ring of at least 2 px that it does not have unfocused
- **AND** no enabled control is skipped

### Requirement: Reduced motion
When the browser asks for reduced motion, Aero pages (currently "/login" and "/styleguide") SHALL run no animation and SHALL keep every transition at 0.01 s or less.

#### Scenario: No animation with reduced motion
- **GIVEN** the browser emulates prefers-reduced-motion: reduce
- **WHEN** "/login" and "/styleguide" are opened and the pointer moves over the buttons
- **THEN** no element, including the background layers, has an animation-name other than "none" or an animation duration above 0
- **AND** every transition lasts 0.01 s or less

### Requirement: Mobile layout
At a 375 px wide viewport, Aero pages (currently "/login" and "/styleguide") SHALL fit without horizontal scrolling, show every control within the viewport width, and give every interactive control a touch area of at least 44x44 px.

#### Scenario: Pages fit a 375 px phone
- **GIVEN** a 375x667 px viewport
- **WHEN** "/styleguide" and "/login" are opened
- **THEN** document.documentElement.scrollWidth is at most 375
- **AND** every control has a non-zero size and lies within the 375 px viewport width
- **AND** the touch area of every interactive control is at least 44x44 px

### Requirement: Background image fallback
When the background image cannot load, the Aero background SHALL show a sky gradient and the page SHALL stay readable and usable.

#### Scenario: The background image is blocked
- **GIVEN** requests for the background image fail
- **WHEN** a visitor opens "/login"
- **THEN** the background's computed background-image contains "linear-gradient"
- **AND** with the class "aero-no-blur" on "<html>", the form text has a contrast ratio of at least 4.5:1 with the panel
- **AND** submitting the right credentials signs the visitor in

### Requirement: Light mode only
The application SHALL always render in light mode: the "<html>" element SHALL never receive the class "dark", and Aero components SHALL look the same if that class is present.

#### Scenario: The system prefers dark
- **GIVEN** the browser emulates prefers-color-scheme: dark
- **WHEN** "/login" and "/styleguide" are opened
- **THEN** "<html>" does not have the class "dark"

#### Scenario: A stored starter-kit preference asks for dark
- **GIVEN** the browser storage holds the starter kit's "appearance" preference set to "dark"
- **WHEN** "/login" and "/styleguide" are opened
- **THEN** "<html>" does not have the class "dark"

#### Scenario: A stray dark class does not change Aero
- **GIVEN** the class "dark" added to "<html>"
- **WHEN** "/login" is opened
- **THEN** the computed background colour of the panel is the same as without the class
- **AND** with the class "aero-no-blur" on "<html>", the panel text has a contrast ratio of at least 4.5:1 with the panel

### Requirement: Asset provenance
Every visual asset file of the Aero look (backgrounds, icons and fonts) SHALL have an entry in the provenance log with its origin (a URL or "hand-made") and the date it was added.

#### Scenario: Every asset is logged
- **GIVEN** the asset folders for backgrounds, icons and fonts
- **WHEN** their files are compared with the provenance log
- **THEN** each file has an entry with its origin and the date it was added

### Requirement: App branding
The signed-in screens SHALL show the app name "AeroPlayer" and SHALL NOT link to the starter kit's repository or documentation.

#### Scenario: No starter-kit links or name
- **GIVEN** the owner signed in
- **WHEN** the owner opens "/dashboard"
- **THEN** the page shows "AeroPlayer"
- **AND** it shows none of "Laravel Starter Kit", "Github Repo" or "Documentation"
- **AND** it has no link to "github.com/laravel/vue-starter-kit" or "laravel.com/docs/starter-kits"
