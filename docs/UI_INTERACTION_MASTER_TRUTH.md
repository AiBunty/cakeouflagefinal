# UI Interaction Master Truth

## Scope
This document defines the default interaction policy for the Cakeouflage storefront and admin panel.

## Core Rule
Button-triggered actions must preserve user context.

That means:
- Do not use full-page reloads as the default success path for inline actions.
- Do not send the viewport back to the top after an action completes.
- Preserve the user's current scroll position for same-page actions, auto-submit filters, and unavoidable reloads.

## Allowed Full Navigations
These are still allowed when intentional:
- Normal page-to-page navigation links.
- Real create/update form submissions where a new server-rendered state is required.
- Authentication flows.
- File exports/downloads.
- Explicit redirect flows required by backend business logic.

## Preferred Pattern For Inline Actions
For approve/reject/archive/refund/restore/toggle-style actions:
1. Submit asynchronously when practical.
2. Show inline or toast feedback.
3. Update only the affected DOM/state when possible.
4. If a reload is still necessary, route it through `window.CakeScrollPreserver.reload()`.

## Scroll Preservation Utility
Shared utility file:
- `/client/assets/js/scroll-preserve.js`

Available helpers:
- `window.CakeScrollPreserver.saveState()`
- `window.CakeScrollPreserver.submitForm(form)`
- `window.CakeScrollPreserver.reload()`
- `window.CakeScrollPreserver.navigate(url)`

## Auto-Submit Filters
Any filter control that auto-submits the current page should use scroll preservation.

Preferred inline pattern:
```html
onchange="window.CakeScrollPreserver ? window.CakeScrollPreserver.submitForm(this.form) : this.form.submit()"
```

## Custom Cake Canonical Route
All client-facing Custom Cake / Build Your Own Cake / Customize CTAs must route to:
- `/custom-cake-inquiry`

Do not introduce alternate customer-facing routes for the same intent unless the canonical route changes globally.

## Category Page Standards
For `/category` pages:
- Search must exist on mobile and desktop/tablet.
- Filters/search/sort must keep existing backend query compatibility.
- Customizable product CTAs may route to `/custom-cake-inquiry`.
- Mobile-first layout quality has priority.

## Regression Checklist
Before shipping UI interaction changes:
- Confirm no unexpected jump-to-top after button actions.
- Confirm auto-submit filters preserve context.
- Confirm search/filter/sort still preserve existing query params.
- Confirm client-facing Custom Cake CTAs still resolve to `/custom-cake-inquiry`.
- Confirm no syntax or runtime errors in touched files.
