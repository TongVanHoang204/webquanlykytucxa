# Auth Two-Column Redesign

Date: 2026-03-29
Project: WEBQuanLyKyTucXa
Scope: `login.php`, `register.php`, and their auth styling layer

## Goal

Redesign the login and registration pages into a modern two-column auth layout.

The redesign must:

- keep the warm, premium tone already established in `assets/css/auth_shell.css`
- make both pages feel like a matched pair
- emphasize system utility rather than generic marketing
- improve layout hierarchy and readability without changing server-side auth behavior

## Constraints

- Keep existing validation and submission logic intact.
- Preserve the current auth flows, links, and form fields.
- Reuse the visual language of the current auth shell rather than replacing it with a cold SaaS style.
- Support desktop, tablet, and mobile cleanly.
- Avoid over-fragmenting the UI into many nested cards.

## Recommended Approach

Use a "poster + form" split layout.

- Left column: a branded utility poster that explains what the student can do in the system.
- Right column: a focused form panel for the actual login or registration task.

This approach gives the pages stronger visual identity than the current single-card layout while staying grounded in the existing warm design language.

## Layout Structure

### Shared Shell

Both `login.php` and `register.php` should share the same outer auth shell.

- A centered main auth panel spans most of the viewport width on desktop.
- The panel is split into two columns inside one large surface.
- The left column occupies roughly 52-58% of width.
- The right column occupies roughly 42-48% of width.
- The shell should feel like one composed object, not two unrelated cards.

### Left Column: Poster

Purpose: establish meaning and value before interaction.

Content order:

1. small overline or product label
2. strong headline in 2-3 lines
3. short supporting description
4. three utility benefits with icons
5. light supporting meta row or trust cues

Visual direction:

- warm, premium background treatment
- soft gradients and subtle highlights
- stronger typographic presence than the form side
- enough contrast for readability, but not dark-mode-heavy

### Right Column: Form Panel

Purpose: provide a clear, low-friction interaction zone.

Content order:

1. compact system label or brand text
2. page title
3. one-line explanatory copy
4. server/client alerts
5. form fields
6. primary CTA
7. social auth section
8. page switch link

Visual direction:

- brighter surface than the poster column
- large radius, soft border, restrained shadow
- clean spacing and highly legible labels
- clear focus states and button hierarchy

## Page-Specific Messaging

### Login Page

Intent: return the student to ongoing work quickly.

Left-column messaging should emphasize:

- resume room registration or request tracking
- review notifications and account activity
- manage student information and billing in one place

Right-column messaging should be direct and compact:

- title centered on logging into the system
- short copy that reinforces fast access to existing tasks

### Register Page

Intent: help a student begin using the system correctly.

Left-column messaging should emphasize:

- starting the dormitory service workflow
- creating one account for room registration, profile, invoices, and notices
- reducing fragmented communication channels

Right-column messaging should feel slightly more welcoming than login:

- title centered on account creation
- short copy that explains the account will be used for dormitory operations

## Information Hierarchy

The redesign should create the following priority order:

1. left-column headline
2. right-column page title
3. form fields and primary CTA
4. benefit list
5. social auth and page switch links

This keeps the first impression strong without distracting from the main action.

## Form Composition Rules

- Do not change field semantics or validation behavior.
- Keep labels visible above or clearly attached to inputs.
- Increase vertical rhythm compared with the current implementation.
- Use soft, rounded inputs aligned with the existing warm auth theme.
- Keep the password toggle, error states, and success states, but restyle them to fit the new shell.

Register-page composition:

- desktop may use two columns only for compatible field groupings such as email and phone
- fields that benefit from uninterrupted reading should remain full width
- the longer form should expand vertically inside the same shared auth shell

## Responsive Behavior

### Desktop

- Preserve the full two-column composition.
- The auth shell should feel spacious but not stretched edge to edge.
- The poster side remains visually dominant.

### Tablet

- Reduce gaps and rebalance column widths as space tightens.
- Keep two columns if the width still supports comfortable form entry.

### Mobile

- Collapse to one column.
- Poster content moves above the form.
- Poster copy is reduced to headline, short description, and three benefits.
- Form remains the primary interactive block.
- Buttons and inputs remain full width.

## Alerts, States, and Motion

- Server and client validation states remain on the form side.
- Alert components should be visually integrated into the warm palette.
- Focus, hover, and pressed states should be noticeable but restrained.
- Motion should be limited to soft entrance and interaction feedback.
- No ornamental animation that competes with form completion.

## Styling Direction

The redesign should continue the established auth-shell visual family:

- warm neutrals instead of cold white/blue SaaS defaults
- premium serif display treatment for major headings where appropriate
- modern sans-serif for form content
- subtle depth via gradients, blur, and soft shadows
- polished but practical spacing

Avoid:

- dashboard-card mosaics
- overly dark tech-product styling
- heavy decorative illustrations
- generic split-screen templates

## Implementation Targets

Expected implementation areas:

- update `login.php` markup structure to support the shared two-column shell
- update `register.php` markup structure to match that shell
- consolidate or refactor auth CSS so both pages share the same layout system
- preserve page-specific field groupings and validation messaging

## Testing Expectations

Implementation should verify:

- login form still submits and displays errors correctly
- registration form still submits and displays server/client validation correctly
- password toggle still works on both pages
- social auth links remain accessible
- responsive layout works on desktop and mobile widths

## Scope Boundaries

In scope:

- layout
- content hierarchy
- presentation styles for auth pages
- responsive behavior of the auth shell

Out of scope:

- auth business logic changes
- validation rule changes
- onboarding flow changes
- unrelated sitewide styling work
