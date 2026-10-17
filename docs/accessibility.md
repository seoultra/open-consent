# Accessibility

What is implemented and tested:

- Banner: `role="dialog"`, `aria-modal="false"`, labelled by its heading and described
  by its text; receives focus when first shown so keyboard and screen-reader users
  land on it; focus returns to the previous element after a choice.
- Tab order inside the banner: policy links → **Reject all** → **Accept all** →
  Customize. Accept and Reject share one visual style (tested for identical computed
  colours, size and weight).
- Preferences: modal `role="dialog"` with `aria-modal="true"`, focus moved in, **focus
  trap** (Tab/Shift+Tab cycle), **Escape** closes and returns focus to the opener,
  overlay click closes.
- Category toggles are native checkboxes with `role="switch"`, labelled by the category
  heading and described by its description; visible "On/Off" text; optional categories
  are never pre-selected.
- Visible focus rings (`:focus-visible`, 3 px), Windows High Contrast (`forced-colors`)
  borders, `prefers-reduced-motion` disables animation, 44 px minimum touch targets,
  RTL via the page's `dir` attribute, page language via `<html lang>`.

Not yet done: a formal audit with screen readers (NVDA/JAWS/VoiceOver/TalkBack) and an
axe-core run in CI. Please report issues.
