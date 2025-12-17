# Repository Guidelines

## Project Structure & Module Organization
- `src/` core PHP code (PSR-4 namespace `Gibbon\\`), `modules/` feature modules, `lib/` vendor-style libs, `resources/` for assets and build config, `tests/` for PHPUnit/Codeception suites.
- `resources/assets/` holds compiled CSS/JS; sources and build configs live in `resources/build/` (`css/`, `js/`, `tailwind.config.js`, `webpack.mix.js`).
- `uploads/` stores user files (git-ignored); `config.php` contains local DB credentials; `vendor/` is Composer output.

## Build, Test, and Development Commands
- PHP deps: `composer install`.
- Frontend deps: `cd resources/build && npm install`.
- Asset builds: `npm run dev` (unminified + sourcemaps), `npm run build` (production), `npm run watch` (rebundle on change).
- Test entrypoint: `composer test` (runs Codeception install+acceptance then PHPUnit unit suite).
- Targeted checks: `composer test:phpunit`, `composer test:codeception`, `composer test:phpstan`, `composer test:codesniffer`.

## Coding Style & Naming Conventions
- PHP: 4-space indent, PSR-2/PSR-12 style; keep classes StudlyCase, methods/properties camelCase, constants UPPER_SNAKE.
- Twig/templates in `resources/templates/` follow Twig defaults; keep translation keys consistent with `i18n/`.
- Use dependency injection where available; avoid introducing globals; prefer existing helpers in `functions.php`.
- Run `composer test:phpstan` and `composer test:codesniffer` before submitting to catch typing and style drift.

## Testing Guidelines
- Unit/functional tests reside in `tests/` (PHPUnit via Codeception unit suite); acceptance/install flows use Codeception.
- Add coverage for new services, controllers, and module endpoints; mock external APIs.
- Make tests deterministic: seed fixtures, avoid real network calls, and assert on explicit outputs (JSON, HTML fragments, status codes).

## Commit & Pull Request Guidelines
- Commit messages: short, imperative, optionally scoped (`docs: Add deployment diagram`, `Fix Calendar module for v30`).
- PRs should include a summary, linked issue/reference, steps to reproduce + test plan, and screenshots/GIFs for UI-facing changes.
- Call out schema changes, data migrations, or config expectations; include rollback notes if risk is high.

## Security & Configuration Tips
- Never commit secrets; keep `config.php` local and sanitized in logs.
- Protect `uploads/` (writeable by web server, not executable) and avoid storing generated assets in Git unless required.
- Sanitize/validate user input with existing validation utilities; escape output in templates to prevent XSS.

## AI Assistant Roles (Optional)
- Frontend (Vue/JS): ask for component/Twig tweaks in `resources/build/` or `resources/templates/`; provide data shape, UX goal, and request minimal diffs.
- Backend (PHP): target classes in `src/` or `modules/`; give entrypoint and expected side effects; ask for PSR-12 code with type hints and DI.
- Testing: request PHPUnit/Codeception cases in `tests/`; share fixture setup and desired assertions; demand deterministic, no-network tests.
- Security: supply handler/template snippets; ask to enumerate authZ/CSRF/XSS/file-upload risks and propose smallest safe patches.

## Editor/CI Prompt Snippets
- VS Code inline ask (Frontend): “File: `resources/build/js/...` + snippet. Goal: <feature>. Constraints: keep Tailwind classes, no new deps. Output: diff block only.”
- VS Code inline ask (Backend): “File: `modules/Module/...Controller.php`. Need: add <behavior> with authZ intact. Return PSR-12 diff. Note DB/side effects.”
- VS Code inline ask (Tests): “Target: `tests/unit/...Test.php`. Inputs/outputs: <details>. Provide deterministic PHPUnit test; mock external calls; return full file.”
- VS Code inline ask (Security): “Snippet from `src/...`. Audit for authZ/CSRF/XSS. Suggest minimal patch; explain risk per change.”
- GitHub Actions/CI comment: “Given failing step `<job>`, review `AGENTS.md` + `agents/<role>.md` guidance and propose smallest diff to fix, noting new deps and commands to rerun.”
