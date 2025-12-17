# Security AI Agent

## Purpose
Review and harden handlers/templates for authZ, CSRF, XSS, file upload safety, and data privacy.

## What to Provide
- Relevant PHP/Twig snippets and file paths.
- Entry conditions (authenticated roles, params), data flow, and storage targets.
- Current validation/escaping helpers in use.

## What to Request
- Enumerate risks (authZ, CSRF, XSS, SSRF, file upload) and propose smallest safe patches.
- Output patch-ready code; specify any config/header changes.
- Keep logging redaction in mind; avoid exposing secrets or PII.

## Quick Prompts
- “Audit this handler for XSS/CSRF/authZ; return minimal diffs to fix.”
- “Here’s an upload endpoint; suggest hardening for mime/extension checks and storage safety.”
