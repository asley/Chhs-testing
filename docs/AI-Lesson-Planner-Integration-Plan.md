# AI Teacher Lesson Planner Integration Plan

## Goal

Integrate the existing `aiTeacher` module into Gibbon's `Planner` workflow as a drafting assistant for lesson content and homework. The teacher should be able to start from a saved Planner lesson, open an AI generator linked to that lesson, use CSEC syllabus/unit context through the AI subject agent, preview the generated result, then insert approved draft content into the Planner lesson fields.

The feature should avoid direct core rewrites where possible so future Gibbon updates are easier to merge.

## Current State

### Planner

The Planner module stores dated lessons in `gibbonPlannerEntry`.

Important columns:

- `name`: lesson title.
- `summary`: short lesson summary.
- `description`: main lesson content.
- `teachersNotes`: private teacher notes.
- `homework`: `Y` or `N`.
- `homeworkDueDateTime`: homework deadline.
- `homeworkDetails`: homework instructions.
- `homeworkTimeCap`: estimated minutes.
- `gibbonUnitID`: selected curriculum unit.

Relevant files:

- `modules/Planner/planner_add.php`: lesson creation form.
- `modules/Planner/planner_edit.php`: lesson edit form.
- `modules/Planner/planner_addProcess.php`: inserts `gibbonPlannerEntry`.
- `modules/Planner/planner_editProcess.php`: updates `gibbonPlannerEntry`.
- `modules/Planner/planner_view_full.php`: view lesson page.
- `modules/Planner/planner_view_full_smartProcess.php`: updates smart blocks.
- `modules/Planner/units_edit_working.php`: unit/block editing.

Planner units and blocks already map well to syllabus structure:

- `gibbonUnit`: syllabus module/unit.
- `gibbonUnitBlock`: objective/topic block.
- `gibbonUnitClass`: running unit assigned to a class.
- `gibbonUnitClassBlock`: class-level block linked to a dated planner entry.
- `gibbonPlannerEntryOutcome`: lesson-to-outcome links.

### aiTeacher

The `aiTeacher` module already has AI pages and settings:

- `modules/aiTeacher/resource_generator.php`: resource generator UI.
- `modules/aiTeacher/resource_generator_ajax.php`: AJAX handler for generated assessments.
- `modules/aiTeacher/curriculum_support.php`: lesson-plan generation page.
- `modules/aiTeacher/moduleFunctions.php`: current global helper functions.
- `modules/aiTeacher/src/DeepSeekAPI.php`: DeepSeek chat completion client.
- `modules/aiTeacher/src/OpenAIAPI.php`: OpenAI client.
- `modules/aiTeacher/settings.php`: API key settings.

Current limitation: the generator logic is page-oriented and global-function-based. The Planner should not duplicate this code or call the existing page UI as its main integration. The generator should first be extracted into a reusable service, then both `aiTeacher` and `Planner` should call that service.

### CSEC Syllabus Context

There is already local CSEC syllabus/planner work:

- `.claude/agents/csec-planner-builder.md`: local agent guidance for building Planner units from CSEC IT/EDPM syllabi.
- `scripts/seed-csec-planner-2026-2027.php`: seed script for CSEC IT and EDPM units/outcomes.
- `docs/syllabus copy/`: local syllabus PDFs and drafts.

This is useful for building Planner units/outcomes. For live lesson generation, the AI feature should read structured Planner data first, not raw PDF files during every request.

## Recommended Architecture

### Design Decision

Use `aiTeacher` as the AI service owner and add a thin Planner integration layer.

Do not move all AI code into Planner, and do not make aiTeacher the owner of Planner lesson records. Instead:

1. `aiTeacher` owns providers, prompts, AI settings, logging, and subject-agent rules.
2. `Planner` owns lesson records, form fields, permissions, and the final save.
3. A new shared service converts Planner context into a structured AI request.
4. Teachers review and edit generated draft content before the normal Planner save.

This keeps the feature modular. If Gibbon updates Planner, the modified surface is small.

## aiTeacher Role In Planner

For v1, aiTeacher is a draft generator for two Planner areas: Lesson Content and Homework.

Lesson Content responsibilities:

- Generate a lesson title for `name` from the selected unit/topic.
- Generate a short lesson summary for `summary`.
- Generate student-facing lesson content for `description`.
- Generate private teacher notes for `teachersNotes`.
- Include objectives, explanations, classroom activities, checks for understanding, examples, and CSEC alignment.

Homework responsibilities:

- Generate student-facing homework instructions for `homeworkDetails`.
- Suggest a realistic time estimate for `homeworkTimeCap`.
- Optionally include answer keys, marking guidance, or remediation notes in `teachersNotes`, not in student-facing homework.
- Suggest homework based on the selected unit/block and lesson content, but leave final due date, submission settings, and visibility settings to the teacher.

Out of scope for v1:

- Directly inserting rows into `gibbonPlannerEntry`.
- Directly changing homework submission settings.
- Replacing the standalone assessment/resource generator.
- Generating Markbook columns.

## User Workflow

1. Teacher opens `Planner > Edit Lesson Plan` for a saved lesson.
2. The lesson already has a class and should have a selected unit.
3. Planner shows an AI action button near `Lesson Content`, for example `Generate with AI`.
4. Button links to `aiTeacher`'s hidden Planner generator page with Planner context:
   - `gibbonPlannerEntryID`.
   - `gibbonCourseClassID`.
   - `gibbonUnitID`.
   - lesson date/time.
   - course/class name.
5. AI page loads syllabus/unit context:
   - subject from course name.
   - unit title/details/tags.
   - unit blocks/objectives.
   - existing lesson title/summary if available.
6. Teacher chooses output type:
   - lesson content only.
   - homework only.
   - lesson content and homework.
7. AI subject agent generates structured output.
8. Teacher reviews and edits generated text.
9. Teacher inserts content into:
   - `name`.
   - `summary`.
   - `description`.
   - `teachersNotes`.
   - `homeworkDetails`.
   - `homeworkTimeCap`.
10. Teacher returns to Planner and saves through existing `planner_editProcess.php`.

After the edit flow is stable, the same pattern can be added to `Planner > Add Lesson Plan`.

## Implementation Phases

### Phase 1: Stabilize aiTeacher Generator Services

Create reusable service classes instead of keeping the main logic inside pages.

This phase must also close two pre-existing security gaps before this feature ships. These do not need to block writing the new service classes, but they should land with the Phase 1 refactor work because the Planner integration would otherwise inherit and amplify them:

- Restore and enforce access checks on generator endpoints. `resource_generator_ajax.php` currently has a disabled access-check comment, and the new Planner integration must not inherit that pattern.
- Reduce AI prompt/response log exposure. API clients and generator helpers currently log prompts and raw responses; this can expose syllabus drafts, teacher notes, or student data once Planner context is included. Also untrack or clear committed runtime logs such as `modules/aiTeacher/php-error.log`, because `*.log` in `.gitignore` does not affect files already tracked by Git.

New files:

- `modules/aiTeacher/src/Services/AITeacherSettingsService.php`
- `modules/aiTeacher/src/Services/AITeacherProvider.php`
- `modules/aiTeacher/src/Services/CsecSubjectAgentService.php`
- `modules/aiTeacher/src/Services/LessonContentGenerator.php`
- `modules/aiTeacher/src/Services/AITeacherLogger.php`

Responsibilities:

- `AITeacherSettingsService`: read API keys and module settings from `aiTeacherSettings`.
- `AITeacherProvider`: choose DeepSeek/OpenAI and call the provider client.
- `CsecSubjectAgentService`: select subject-specific prompt rules.
- `LessonContentGenerator`: build lesson/homework generation prompts and normalize output.
- `AITeacherLogger`: log request metadata safely, mask secrets, avoid full prompt/response logging by default, and allow verbose diagnostics only through an explicit admin setting.

Keep `resource_generator.php`, `resource_generator_ajax.php`, and `curriculum_support.php`, but move their generation logic toward these services as they are touched. The immediate requirement is that any touched generator endpoint keeps working access checks and production-safe logging.

Phase 1 is not complete until existing aiTeacher generation endpoints have working permission checks and production-safe logging.

### Phase 2: Add Planner Context Builder

Add a service that can safely load Planner context for the current teacher.

Possible file:

- `modules/aiTeacher/src/Services/PlannerContextBuilder.php`

Inputs:

- `gibbonCourseClassID`
- optional `gibbonPlannerEntryID`
- optional `gibbonUnitID`
- current `gibbonPersonID`

Output shape:

```php
[
    'course' => [
        'id' => '...',
        'name' => 'Information Technology',
        'nameShort' => 'IT',
    ],
    'class' => [
        'id' => '...',
        'nameShort' => '4A',
    ],
    'unit' => [
        'id' => '...',
        'name' => 'Module 1: Fundamentals of Information Technology',
        'description' => '...',
        'details' => '...',
        'tags' => '...',
    ],
    'blocks' => [
        [
            'title' => '1.1 Identify types of computer systems',
            'type' => 'Learning',
            'contents' => '...',
            'teachersNotes' => '...',
            'sequenceNumber' => 1,
        ],
    ],
    'lesson' => [
        'id' => '...',
        'name' => '...',
        'summary' => '...',
        'date' => '...',
        'timeStart' => '...',
        'timeEnd' => '...',
    ],
]
```

Access control must match Planner rules:

- Teachers can generate only for classes they teach.
- Admin/all-class Planner users can generate for all classes if their current Planner action allows it.
- Students and parents should not access teacher generation endpoints.

### Phase 3: Add aiTeacher Planner Generator Endpoint

Add a dedicated internal page/AJAX action in `aiTeacher`.

New files:

- `modules/aiTeacher/planner_generate.php`
- `modules/aiTeacher/planner_generate_ajax.php`

Add a new hidden action row to `modules/aiTeacher/manifest.php`:

- Name: `Planner AI Generator`
- Category: `Features`
- `URLList`: `planner_generate.php,planner_generate_ajax.php`
- `entryURL`: `planner_generate.php`
- `entrySidebar`: `N`
- `menuShow`: `N`
- Teacher/Admin permissions: `Y`
- Student/Parent permissions: `N`

Add the same action in `modules/aiTeacher/CHANGEDB.php` for module upgrades.

The endpoint should return structured JSON, not only Markdown:

```json
{
  "success": true,
  "lesson": {
    "name": "Input Devices and Their Uses",
    "summary": "Students classify input devices and match them to real-world use cases.",
    "description": "<h3>Learning Objectives</h3>...",
    "teachersNotes": "<p>Misconception to watch...</p>"
  },
  "homework": {
    "enabled": true,
    "details": "<p>Complete the worksheet...</p>",
    "timeCap": 30
  },
  "meta": {
    "provider": "deepseek",
    "subjectAgent": "CSEC Information Technology",
    "unitID": "..."
  }
}
```

### Phase 4: Add Planner UI Button

Add an AI button first in:

- `modules/Planner/planner_edit.php`

Add support later in:

- `modules/Planner/planner_add.php`

Recommended placement:

- near the `Lesson Content` heading.
- only show when `aiTeacher` is installed and the current user has access to `/modules/aiTeacher/planner_generate.php`.
- only show when `gibbonPlannerEntryID`, `gibbonCourseClassID`, and `gibbonUnitID` are available.

Example action behavior:

- Edit page with existing lesson: pass `gibbonPlannerEntryID`, `gibbonCourseClassID`, and `gibbonUnitID` to `planner_generate.php`.
- `planner_generate.php` displays generated draft fields and a return link to the Planner edit page.
- The first implementation may render copy-ready draft fields on the generator page. A later enhancement can insert fields into the open Planner form client-side.

Use JavaScript to populate existing editor fields. If the editor is TinyMCE/CKEditor through Gibbon's `addEditor()`, use the editor API rather than plain textarea assignment.

Fields to populate:

- `name`
- `summary`
- `description`
- `teachersNotes`
- `homework`
- `homeworkDetails`
- `homeworkTimeCap`

Do not save directly to the database from the AI endpoint. Let the teacher click the normal Planner save button.

### Phase 5: Subject-Agent and Syllabus Mapping

Create a subject mapping layer instead of hard-coding subject names inside form markup.

Possible file:

- `modules/aiTeacher/src/Services/CsecSubjectMap.php`

Map common Gibbon course names to subject agents:

- `Information Technology`, `IT` -> CSEC Information Technology agent.
- `EDPM` -> CSEC EDPM agent.
- `Mathematics` -> CSEC Mathematics agent.
- `English A` -> CSEC English A agent.
- `Social Studies` -> CSEC Social Studies agent.
- `Caribbean History` -> CSEC Caribbean History agent.

Prompt strategy:

- System prompt: "You are a CSEC subject teacher generating classroom-ready lesson content aligned to the CXC syllabus."
- Context prompt: include course, class, unit, unit blocks, outcomes, date/time, and desired output type.
- Output contract: require strict JSON with `lesson` and `homework` keys.
- Safety rule: if syllabus context is missing, say what is missing and generate only a generic draft marked as not syllabus-verified.

Important: avoid reading large syllabus PDFs during live teacher requests. The syllabi should be transformed into Planner units/blocks/outcomes first. The live generator should consume those structured records.

### Phase 6: Optional Direct Insert Endpoint

Only add this after the review-and-insert workflow works.

Possible file:

- `modules/aiTeacher/planner_apply_ajax.php`

Purpose:

- Save approved AI output directly to an existing `gibbonPlannerEntry`.

Constraints:

- Require `gibbonPlannerEntryID`.
- Re-check Planner edit access.
- Validate CSRF/token handling.
- Sanitize HTML through Gibbon's `Validator`.
- Store an audit log with before/after summaries.

This is useful later, but the first version should avoid direct writes.

## Database Changes

Minimum required:

- Add `Planner AI Generator` action to aiTeacher manifest and CHANGEDB.

Recommended audit table:

```sql
CREATE TABLE IF NOT EXISTS `aiTeacherPlannerGeneration` (
  `aiTeacherPlannerGenerationID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `gibbonPersonID` int(10) unsigned NOT NULL,
  `gibbonPlannerEntryID` int(14) unsigned zerofill DEFAULT NULL,
  `gibbonCourseClassID` int(8) unsigned zerofill NOT NULL,
  `gibbonUnitID` int(10) unsigned zerofill DEFAULT NULL,
  `subject` varchar(100) NOT NULL,
  `outputType` varchar(50) NOT NULL,
  `promptHash` varchar(64) DEFAULT NULL,
  `provider` varchar(50) DEFAULT NULL,
  `status` enum('Success','Error') NOT NULL,
  `error` text,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`aiTeacherPlannerGenerationID`),
  KEY `gibbonPersonID` (`gibbonPersonID`),
  KEY `gibbonPlannerEntryID` (`gibbonPlannerEntryID`),
  KEY `gibbonCourseClassID` (`gibbonCourseClassID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

Do not store full prompts with student data by default. Store a hash and safe metadata unless you explicitly need full audit replay.

## Files Likely To Change

aiTeacher:

- `modules/aiTeacher/manifest.php`
- `modules/aiTeacher/CHANGEDB.php`
- `modules/aiTeacher/resource_generator_ajax.php`
- `modules/aiTeacher/curriculum_support.php`
- `modules/aiTeacher/moduleFunctions.php`
- `modules/aiTeacher/src/DeepSeekAPI.php`
- new service files under `modules/aiTeacher/src/Services/`
- new `planner_generate.php`
- new `planner_generate_ajax.php`

Planner:

- `modules/Planner/planner_edit.php`
- optionally `modules/Planner/js/module.js`
- optionally `modules/Planner/css/module.css`
- later `modules/Planner/planner_add.php`

Tests:

- `tests/unit/Modules/AITeacher/LessonContentGeneratorTest.php`
- `tests/unit/Modules/AITeacher/PlannerContextBuilderTest.php`
- a small access-control test for the AJAX endpoint if the existing test harness supports it.

## Future Update Strategy

To make future Gibbon updates manageable:

1. Keep all AI logic inside `modules/aiTeacher/src/Services`.
2. Keep Planner edits limited to adding one button/container and one JS include.
3. Do not modify Planner process files for the first version.
4. Do not change core `src/` unless a reusable Gateway already exists and the change is small.
5. Use module `CHANGEDB.php` for aiTeacher database upgrades instead of editing production DB manually.
6. Keep local syllabus PDFs and generated drafts out of Git unless they are approved project assets.
7. Document every touched Planner file because those are the files most likely to conflict with future Gibbon upgrades.

## Risks

- Current aiTeacher code mixes global functions, page rendering, AJAX, and provider calls. Refactoring to services is the most important first step.
- AI output must be treated as draft content. It should never directly overwrite Planner content without teacher review.
- Large syllabus PDFs should not be loaded into each generation request. Use structured Planner unit/block data.
- Some course names may not match CSEC subject names exactly. Add a configurable subject map.

## Suggested Build Order

1. Harden existing aiTeacher generator endpoints: restore access checks, stop full prompt/response logging by default, and remove tracked runtime log exposure.
2. Create `LessonContentGenerator` with a hard-coded fake provider for tests.
3. Add `PlannerContextBuilder` and verify it returns class/unit/block context for a teacher.
4. Add `planner_generate_ajax.php` returning JSON, with strict access checks.
5. Add `planner_generate.php` as a simple review UI.
6. Add the Planner button to `planner_edit.php` first.
7. Render copy-ready draft fields for `name`, `summary`, `description`, `teachersNotes`, `homeworkDetails`, and `homeworkTimeCap`, plus a return link to Planner edit.
8. Add client-side field insertion as an enhancement after the generator page works.
9. Add `planner_add.php` support after edit flow works.
10. Refactor existing `resource_generator_ajax.php` and `curriculum_support.php` to call the same service if they were not already refactored during hardening.
11. Add tests and update `modules/aiTeacher/README.md`.

## First Version Acceptance Criteria

- Teacher sees `Generate with AI` from Planner edit when `aiTeacher` is installed and permitted.
- Generated drafts can be reviewed, edited, and applied directly to the Planner lesson without manual copy and paste.
- Generator receives class, unit, and existing lesson context.
- Generated output is CSEC-subject-aware and uses Planner unit/block context.
- Teacher can preview output before inserting it.
- Generated draft includes `name`, `summary`, `description`, `teachersNotes`, `homeworkDetails`, and `homeworkTimeCap`.
- No generated content is saved to `gibbonPlannerEntry` until teacher submits the normal Planner form.
- Unauthorized users cannot call the generator endpoint directly.
- API key missing state shows a clear admin-facing error.
- Generation attempts are logged without exposing API secrets.
