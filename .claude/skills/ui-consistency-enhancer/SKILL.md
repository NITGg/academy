# Skill: UI Consistency & Visual Identity Enhancer

## Purpose

Enhance the existing project UI, improve visual quality, and create a consistent visual identity across all pages and components.

The goal is not only to review the UI, but to actively improve and refactor it while preserving the current project functionality, business logic, and overall product structure.

---

## Main Objectives

When this skill is used, you must:

1. Analyze the current project UI and design patterns.
2. Identify visual inconsistencies between pages and components.
3. Define a unified visual identity for the project.
4. Refactor shared components to enforce consistency.
5. Improve usability, spacing, typography, responsiveness, and accessibility.
6. Test the updated UI on different screen sizes.
7. Avoid unnecessary redesigns that conflict with the current product identity.

---

## Core Rules

### 1. Preserve Functionality

Do not change:

* Business logic.
* API integrations.
* Data-fetching behavior.
* Form submission logic.
* Authentication or authorization logic.
* Routes or page navigation.
* Existing user permissions.
* Backend contracts.

UI improvements must not break existing functionality.

---

### 2. Understand the Existing Design Before Editing

Before making changes:

* Inspect the project structure.
* Identify the frontend framework and UI libraries.
* Review shared components.
* Review the global stylesheet, theme configuration, Tailwind configuration, design tokens, and CSS variables.
* Inspect several pages to understand the current visual identity.
* Identify duplicated UI implementations.
* Identify inconsistent component styles.

Do not start editing individual pages before understanding the project's overall design system.

---

## Visual Identity Standardization

Create or improve a shared design system that defines:

* Primary color.
* Secondary color.
* Accent color.
* Background colors.
* Surface and card colors.
* Text colors.
* Border colors.
* Success, warning, error, and information colors.
* Font family.
* Font sizes.
* Font weights.
* Border radius.
* Shadows.
* Spacing scale.
* Icon sizes.
* Container widths.
* Breakpoints.
* Transition durations.
* Focus states.
* Hover and active states.

Prefer using:

* CSS variables.
* Tailwind theme tokens.
* Theme configuration.
* Shared utility classes.
* Reusable variants.

Avoid hardcoded colors and arbitrary values repeated across components.

Example:

```css
:root {
  --color-primary: ...;
  --color-primary-hover: ...;
  --color-background: ...;
  --color-surface: ...;
  --color-border: ...;
  --color-text-primary: ...;
  --color-text-secondary: ...;

  --radius-sm: ...;
  --radius-md: ...;
  --radius-lg: ...;

  --shadow-sm: ...;
  --shadow-md: ...;
}
```

Use the project's existing styling system rather than introducing a new one unless clearly necessary.

---

## Buttons

All buttons across the project must use the same shared button component or the same centralized styling system.

Standardize button variants such as:

* Primary.
* Secondary.
* Outline.
* Ghost.
* Destructive.
* Success.
* Link.
* Icon-only.

Standardize:

* Height.
* Padding.
* Border radius.
* Font size.
* Font weight.
* Icon size.
* Icon spacing.
* Loading state.
* Disabled state.
* Hover state.
* Active state.
* Focus-visible state.

Example variants:

```tsx
<Button variant="primary">Save</Button>
<Button variant="secondary">Cancel</Button>
<Button variant="outline">View Details</Button>
<Button variant="destructive">Delete</Button>
<Button variant="ghost" size="icon">
  <Icon />
</Button>
```

Do not allow every page to define its own button style.

Use the same action hierarchy everywhere:

* Primary action: filled primary button.
* Secondary action: outline or secondary button.
* Dangerous action: destructive variant.
* Low-priority action: ghost or text button.

Avoid using multiple primary buttons in the same section unless necessary.

---

## Form Controls

Standardize all:

* Inputs.
* Textareas.
* Select fields.
* Search fields.
* Date pickers.
* Checkboxes.
* Radio buttons.
* Switches.
* Upload fields.
* Autocomplete fields.

Each field should consistently support:

* Label.
* Placeholder.
* Helper text.
* Error message.
* Required indicator.
* Disabled state.
* Read-only state.
* Focus state.
* Validation state.

Use consistent:

* Height.
* Padding.
* Border style.
* Border radius.
* Font size.
* Label spacing.
* Error color.
* Focus ring.

Do not create custom input styles inside individual pages when a shared form component already exists.

---

## Tables

All tables must follow one visual standard.

Standardize:

* Table header.
* Row height.
* Cell padding.
* Alignment.
* Typography.
* Borders.
* Striped or non-striped behavior.
* Hover state.
* Selected row state.
* Empty state.
* Loading state.
* Pagination.
* Filters.
* Search.
* Sorting indicators.
* Actions menu.
* Responsive behavior.

Table actions should use the same pattern everywhere, such as:

* A three-dot dropdown menu.
* Consistent icon buttons.
* Tooltips for unclear icons.
* Confirmation dialogs for destructive actions.

Avoid placing many full-width action buttons inside each row.

On small screens:

* Allow safe horizontal scrolling, or
* Transform rows into cards when appropriate.

Never allow tables to break the page layout.

---

## Cards

Create a shared card style for:

* Dashboard statistics.
* Form sections.
* Content blocks.
* Settings groups.
* Profile information.
* Reports.
* List items.

Standardize:

* Background.
* Border.
* Shadow.
* Border radius.
* Padding.
* Header style.
* Title size.
* Description size.
* Footer actions.

Cards should not have random shadow, radius, or padding values across different pages.

---

## Modal, Dialog, Drawer, and Sheet Components

Use consistent patterns for:

* Create forms.
* Edit forms.
* Delete confirmation.
* Details views.
* Filters.
* Mobile navigation.

Standardize:

* Header.
* Title.
* Description.
* Close button.
* Content spacing.
* Footer.
* Action order.
* Width.
* Mobile behavior.

For destructive confirmation dialogs:

* Explain what will be deleted.
* Mention whether the action is irreversible.
* Use a destructive confirmation button.
* Keep the cancel action visually secondary.

---

## Page Layout

All pages should use a consistent layout structure:

```text
Page Header
├── Title
├── Description or breadcrumb
└── Primary actions

Optional Filters / Toolbar

Main Content
├── Cards
├── Table
├── Form
└── Empty or loading state
```

Standardize:

* Page maximum width.
* Horizontal padding.
* Vertical spacing.
* Header alignment.
* Section spacing.
* Content hierarchy.

Do not use different page padding values without a valid reason.

---

## Navigation

Review and improve:

* Sidebar.
* Navbar.
* Breadcrumbs.
* Tabs.
* Mobile menu.
* Active navigation state.
* Collapsed sidebar state.

Ensure:

* The active page is clearly visible.
* Navigation icons have consistent sizes.
* Labels are aligned.
* Hover states are consistent.
* Mobile navigation is usable.
* Long menu labels do not break the layout.
* Nested navigation is easy to understand.

---

## Typography

Create a clear typography hierarchy.

Standardize:

* Page title.
* Section title.
* Card title.
* Body text.
* Supporting text.
* Labels.
* Table text.
* Captions.
* Error messages.

Avoid:

* Random font sizes.
* Excessive bold text.
* Low-contrast gray text.
* Very long line lengths.
* Too many heading sizes.

Use typography to clarify hierarchy, not decoration.

---

## Spacing and Alignment

Use a consistent spacing scale.

For example:

```text
4px
8px
12px
16px
20px
24px
32px
40px
48px
```

Avoid arbitrary values such as:

```text
13px
17px
23px
29px
```

unless required for a specific design reason.

Check:

* Element alignment.
* Form field spacing.
* Card padding.
* Button alignment.
* Table cell alignment.
* Icon and text alignment.
* Section separation.
* Mobile spacing.

---

## Icons

Use one icon library across the project whenever possible.

Standardize icon sizes, for example:

* Small: 16px.
* Default: 18px or 20px.
* Large: 24px.

Rules:

* Do not mix unrelated icon styles.
* Do not use icons without labels when their meaning is unclear.
* Add tooltips to icon-only actions.
* Keep consistent icon spacing inside buttons.
* Use the same icon for the same action across the application.

For example, do not use different icons for “Edit” on different pages.

---

## Feedback States

Every relevant component should have clear states for:

* Loading.
* Empty data.
* Error.
* Success.
* Disabled.
* No search results.
* Unauthorized access.
* Offline or network failure when relevant.

Use:

* Skeletons for page and card loading.
* Spinners only for small inline actions.
* Toasts for operation feedback.
* Inline messages for form errors.
* Empty-state illustrations or icons when appropriate.

Do not leave blank spaces while content is loading.

---

## Responsive Design

Test and improve the UI for:

* Small mobile screens.
* Large mobile screens.
* Tablets.
* Small laptops.
* Desktop screens.
* Large desktop screens.

At minimum, review widths close to:

```text
320px
375px
425px
768px
1024px
1280px
1440px
```

Check:

* Navigation.
* Tables.
* Forms.
* Modals.
* Drawers.
* Cards.
* Grid layouts.
* Page headers.
* Buttons.
* Text wrapping.
* Images.
* Charts.
* Filters.
* Pagination.

Rules:

* No horizontal page overflow.
* No clipped content.
* No overlapping elements.
* Touch targets should be easy to use.
* Important actions must remain accessible.
* Forms should use one column on narrow screens when necessary.
* Buttons may become full-width on mobile when appropriate.
* Page headers may stack vertically on mobile.

---

## Accessibility

Improve accessibility without changing the intended design.

Ensure:

* Interactive elements are keyboard accessible.
* Focus-visible states are clear.
* Buttons use real button elements.
* Links use real anchor elements.
* Inputs have labels.
* Icon-only buttons have accessible names.
* Dialogs trap and restore focus correctly.
* Color is not the only way information is communicated.
* Text contrast is readable.
* Error messages are understandable.
* Form validation is associated with the correct field.

Do not remove outlines without providing an accessible focus style.

---

## Dark Mode

If the project supports dark mode:

* Ensure all shared components work correctly in both themes.
* Avoid hardcoded light-theme colors.
* Verify borders, shadows, text contrast, overlays, tables, cards, forms, and dialogs.
* Keep semantic colors understandable in both modes.

If the project does not already support dark mode, do not add it unless explicitly requested.

---

## Component Reusability

When the same UI pattern appears more than once, consider extracting it into a shared component.

Potential shared components include:

* Button.
* IconButton.
* Input.
* Select.
* Textarea.
* SearchInput.
* FormField.
* PageHeader.
* DataTable.
* TableToolbar.
* StatusBadge.
* EmptyState.
* LoadingState.
* ErrorState.
* ConfirmationDialog.
* Card.
* SectionHeader.
* Pagination.
* FilterPanel.
* Breadcrumbs.
* Tooltip.

Do not over-engineer very small or one-time patterns.

---

## Status Badges

Create a consistent status badge system.

Examples:

* Active.
* Inactive.
* Pending.
* Approved.
* Rejected.
* Completed.
* Cancelled.
* Failed.
* Draft.

Each status must use consistent:

* Text.
* Color.
* Background.
* Border.
* Icon, when needed.
* Capitalization.

Do not assign different colors to the same status on different pages.

---

## Charts and Dashboard Components

For dashboards:

* Use consistent card dimensions.
* Keep chart colors aligned with the visual identity.
* Add clear titles and legends.
* Format numbers consistently.
* Avoid unnecessary visual decoration.
* Ensure charts remain readable on small screens.
* Handle empty and loading states.
* Use the same number formatting for currency, percentages, and totals.

---

## Content and Microcopy

Improve unclear UI text when necessary.

Use consistent terminology for actions.

Examples:

* Use either “Create” or “Add” consistently.
* Use either “Remove” or “Delete” based on the actual action.
* Use “Save Changes” for editing.
* Use “Create” for new entities.
* Use “Cancel” consistently.
* Avoid vague labels such as “Submit” when a more specific label is available.

Do not change business terminology without a clear reason.

---

## Implementation Process

Follow this order:

### Phase 1: Audit

Inspect the project and prepare a short internal assessment covering:

* Existing design system.
* Current shared components.
* Repeated styles.
* Main inconsistencies.
* High-impact pages.
* Responsive issues.
* Accessibility issues.

### Phase 2: Foundation

Improve the shared foundation first:

* Theme.
* Colors.
* Typography.
* Spacing.
* Border radius.
* Shadows.
* Shared button variants.
* Shared form controls.
* Shared card styles.
* Shared status badges.

### Phase 3: Shared Components

Refactor reusable components before modifying every page individually.

### Phase 4: Page Enhancement

Update pages to use the shared components and design tokens.

Prioritize:

1. Main dashboard.
2. Frequently used pages.
3. Forms.
4. Tables.
5. Details pages.
6. Settings pages.
7. Less frequently used pages.

### Phase 5: Responsive Review

Test the changed pages across common screen sizes.

### Phase 6: Final Verification

Verify:

* No functionality is broken.
* No TypeScript errors are introduced.
* No linting errors are introduced.
* No layout overflow exists.
* Shared components are used consistently.
* The project still builds successfully.

---

## Decision-Making Rules

When choosing between keeping the current design and changing it:

* Keep good existing patterns.
* Improve inconsistent or weak patterns.
* Prefer incremental improvement over unnecessary full replacement.
* Perform a larger redesign only when the current structure seriously harms usability or consistency.
* Do not redesign only for decoration.
* Every UI change should improve clarity, consistency, usability, accessibility, or responsiveness.

---

## Restrictions

Do not:

* Change backend code without necessity.
* Modify API contracts.
* Remove working features.
* Replace the entire UI library without a strong technical reason.
* Add many new dependencies.
* Mix multiple styling systems unnecessarily.
* Add random animations.
* Add excessive gradients.
* Add excessive shadows.
* Add excessive border radius.
* Use arbitrary colors outside the visual identity.
* Duplicate shared components.
* Create page-specific button styles.
* sacrifice usability for visual appearance.
* hide important actions on mobile.
* change all files before validating the shared foundation.

---

## Expected Output

After completing the task, provide a summary containing:

### Files Updated

List the important files that were modified.

### Shared Components Created or Improved

Mention reusable components and design tokens that were introduced or updated.

### UI Improvements

Explain the main improvements, such as:

* Unified buttons.
* Standardized form controls.
* Consistent cards.
* Improved tables.
* Better spacing.
* Better typography.
* Improved responsiveness.
* Improved accessibility.
* Unified status colors.

### Validation

Mention whether the following were checked:

* Build.
* TypeScript.
* Lint.
* Responsive screens.
* Dark mode, when supported.
* Keyboard navigation.
* Existing functionality.

### Remaining Recommendations

Mention only meaningful improvements that could not safely be completed in the current task.

---

## Task Execution Instruction

Analyze the entire existing frontend project and actively enhance its UI.

Create a unified visual identity and apply it consistently across the project, especially for:

* Buttons.
* Inputs.
* Forms.
* Cards.
* Tables.
* Modals.
* Status badges.
* Page headers.
* Navigation.
* Typography.
* Spacing.
* Loading states.
* Empty states.
* Responsive layouts.

Reuse and improve existing components where possible.

Start by understanding the current UI architecture, then improve the shared design foundation before updating individual pages.

Do not stop after writing a report. Apply the improvements directly to the codebase and validate the result.
