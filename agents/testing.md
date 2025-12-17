# Testing AI Agent

## Purpose
Create deterministic PHPUnit/Codeception tests in `tests/` that cover new services, controllers, and module endpoints.

## What to Provide
- Target class/function and current behavior.
- Fixtures or seed data; expected inputs/outputs; environment assumptions (no real network).
- Preferred test type (unit vs acceptance) and paths.

## What to Request
- Deterministic tests: mock external APIs, fix timestamps/seeds, avoid global state.
- Clear assertions on status codes, payloads, and side effects.
- Minimal fixtures; reuse helpers already in `tests/`.

## Quick Prompts
- “Write a PHPUnit test for `src/...`; cover success and failure; use mocks for external calls; return the file content.”
- “Add a Codeception acceptance test for route X; assume authenticated user role Y; include steps and assertions.”
