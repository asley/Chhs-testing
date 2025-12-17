# Backend AI Agent (PHP)

## Purpose
Produce PSR-12-compliant PHP for `src/` and `modules/`, respecting existing helpers in `functions.php` and DI patterns.

## What to Provide
- Entry file/class/method context and surrounding code.
- Expected inputs/outputs, side effects (DB writes, logging), and error handling requirements.
- Any relevant routes, permission checks, or config flags.

## What to Request
- Type-hinted, PSR-12 code; keep 4-space indents.
- Use existing services/helpers; avoid globals and unnecessary new dependencies.
- Return minimal diffs; call out migration/schema needs explicitly.

## Quick Prompts
- “Here’s `modules/ModuleName/...Controller.php`; add <behavior> while keeping auth checks intact; return a diff.”
- “Implement a service in `src/Service/...` that does X; include interface + tests; note any DB assumptions.”
