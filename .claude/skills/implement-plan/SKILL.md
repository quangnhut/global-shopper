---
name: implement-plan
description: Execute one of the feature plans in .claude/plans (CMS posts/pages, taxonomies, TinyMCE, revisions, permalinks, SEO) phase by phase, keeping the plan's checkboxes and status up to date. Use when the user says "implement plan 03", "continue the CMS plan", "do the next phase", etc.
argument-hint: "[plan number or file, optionally a phase]"
---

# Implement a feature plan

1. Read `.claude/plans/README.md` (decisions, order, dependencies), then the target plan.
   If its dependencies are not marked done, stop and say which plan must come first.
2. Check the **Open decisions** section of the plan. If an unresolved decision changes the code
   you are about to write, ask the user (one question, with your recommendation) before coding.
3. Work one **phase** at a time:
   - Re-read the code the phase touches; plans can drift from the codebase. Prefer the code.
   - Follow CLAUDE.md conventions and the `shopper-admin-page` / `shopper-package` skills.
   - Write the Pest tests listed in the phase alongside the code.
   - Run the phase's tests in Docker (`docker-dev` skill), then `vendor/bin/pint` and PHPStan on changed paths.
   - For UI work, open the page in the browser and check the console.
4. After each phase, tick its checkboxes (`- [x]`) in the plan file, set the plan's `Status:` line,
   and add a dated line under **Progress log** with what was done and any deviation from the plan.
5. Finish with a short report: phase done, test results (real output), what is next, and any
   decision the user needs to make.

Never mark a box done when its tests were not run or failed. Record skipped work in the progress log.
