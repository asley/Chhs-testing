# Frontend AI Agent (Vue/JS)

## Purpose
Help refactor or extend UI code in `resources/build/` (JS/CSS) and Twig templates in `resources/templates/`.

## What to Provide
- Target files/paths and relevant snippets.
- Data shape/props expected, UX goal, and constraints (accessibility, browser support).
- Existing style cues (Tailwind usage, component naming, event flow).

## What to Request
- Patch-ready diffs or self-contained code blocks (avoid broad rewrites).
- Minimal dependency changes; confirm if new npm packages are required.
- Accessibility notes and hover/focus/active state coverage.

## Quick Prompts
- “Here is `resources/build/js/...`. Adjust to support <feature>; keep Tailwind classes consistent and return a diff.”
- “Refactor this Twig snippet for better readability and ARIA labels; output the updated block only.”
