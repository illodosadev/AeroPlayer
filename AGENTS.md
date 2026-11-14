# Agent Instructions — Spec-Driven Development with OpenSpec

This repository follows **spec-driven development**: no code is written until the change is captured as an OpenSpec proposal with specs, design, and tasks. OpenSpec is the machinery that enforces this; the agent executes it.

## The golden rule

> **Spec → Plan → Code. Never skip a stage.**

If a request arrives as "build X" and there is no OpenSpec change for it, your first move is `/opsx-propose` — not writing code.

## Workflow

The full pipeline runs from requirements to merge. Stages 0–1 are optional for trivial changes; stages 2–6 are mandatory.

0. **Discover** — `/req-capture <topic>` interviews the user and writes `backlog/discovery/<topic>.md`. No invented answers: unknowns go to Open questions.
1. **Tasks** — `/task-generate <topic>` slices the discovery into tasks under `backlog/tasks/`. Task IDs are local (`T-<nn>`). `/task-enrich <id>` adds edge cases, estimates, and unhappy paths; `/task-enrich-batch <ids|--draft>` does the same for several tasks at once (all questions up front, then parallel agents); `/review-task <id>` audits;
2. **Propose** — `/opsx-propose <change-name>` creates `openspec/changes/<name>/` with proposal, delta specs, design, and tasks. If the change implements a backlog task, the proposal references its ID and the task frontmatter gets `change: <name>`.
3. **Specify & plan** — Delta specs (requirements + GIVEN/WHEN/THEN) as ADDED/MODIFIED/REMOVED; `design.md` for the approach; `tasks.md` for checkable steps.
4. **Review** — `/review-change <name>` audits the change before implementation.
5. **Implement** — `/opsx-apply`, working directly on `main` (personal project: no feature branches, no PRs). Commit with `/git-commit`: conventional commits whose footer traces `Change:`/`Task:` (tasks.md step)/`Backlog:` (backlog task id).
6. **Deliver** — `/ship` validates, archives the change and closes the backlog task. No PR.

Traceability chain: **Discovery → Task → Change → tasks.md step → Commit**. Note: "task" means a backlog task; tasks.md inside a change holds implementation steps.

## Guided flow

The pipeline is guided: the user should never have to remember what comes next.

- **Every pipeline command ends by suggesting the next step** — one concrete command with a one-line reason (e.g. after `/task-enrich`: "next: `/opsx-propose` to start building").
- **After implementation work** — whenever `/opsx-apply` finishes a step (or any ad-hoc code edit completes), suggest `/git-commit`. When the last step of `tasks.md` is checked, suggest `/ship`. After `/ship`, list pending backlog tasks and suggest the highest-priority one.
- **`/next` is the recovery point** — when the user seems lost, returns after a break, or asks "what now?", run the `/next` logic: inspect git state, task frontmatter, and change artifacts, then report where they are and the single best next action.
- Suggestions are advice, not actions: never run the suggested command without the user asking.

## Rules for agents

- **The repository is public: no sensitive information in versioned artifacts.** Backlog (discovery, tasks), OpenSpec changes, specs, commit messages and code comments MUST NOT contain secrets (keys, tokens, passwords, Client IDs/secrets, private SSH keys), server reconnaissance details (hosting usernames, absolute server paths, IPs, which PHP functions or other security controls are enabled/disabled), or personal data beyond what is already public. Use placeholders (`<usuario>`, `~/domains/...`, `DEPLOY_PATH`) and keep real values in GitHub secrets/variables or the server's `.env`. Describe accepted risks (e.g. unofficial libraries) neutrally, without written admissions of breaching third-party terms. When data read from an account (e.g. via an MCP) informs a decision, record the decision, not the raw data.
- `openspec/specs/` is the source of truth for current system behavior. Read it before proposing changes; never edit it directly — it only changes via `/opsx-archive`.
- Requirements use RFC-2119 keywords (MUST/SHALL/SHOULD/MAY). Each requirement has at least one scenario.
- If during implementation you discover the spec was wrong or incomplete, stop, update the spec, then continue. Do not silently diverge.
- Keep changes small: one concern per change, one change at a time.
- Validate before archiving: `openspec validate <change> --strict`.
- `workflow.yaml` at the repo root defines the commit convention and backlog settings. Pipeline commands read it.
- Task frontmatter (`status`, `change`, `id`) is the pipeline state of a backlog task. Commands keep it updated; don't bypass it.
- Work directly on `main`; do not create feature branches or PRs unless the user asks.
- **Client-facing content language**: any command or agent that generates text exposed to the client (discovery docs, tasks) MUST ask the user whether to write it in castellano or English before generating — every time, preselecting `content.default_language` from `workflow.yaml`. Never assume the language.

## Repository layout

| Path | Purpose |
|------|---------|
| `backlog/` | Upstream product artifacts: discovery docs and tasks |
| `openspec/specs/` | Current behavior, source of truth |
| `openspec/changes/` | In-flight changes (proposal, specs, design, tasks) |
| `openspec/changes/archive/` | Completed changes, audit history |
| `templates/` | Discovery and task templates |
| `workflow.yaml` | Tool-agnostic pipeline config (commits, backlog) |
| `.claude/` | Claude Code commands, agents, and OpenSpec skills |

## Code conventions

- **CQRS:** every use case is a **Command** (changes state) or a **Query** (reads state), each with its own Handler, in `app/Application/<Context>/{Commands,Queries}`.
  - Entry points dispatch them through `App\Bus\CommandBus::dispatch()` and `App\Bus\QueryBus::ask()`. Entry points are controllers, middleware, form requests and console commands.
  - A command handler returns nothing. A failure is an exception extending `App\Application\UseCaseFailed`, in `Exceptions/`, which the entry point turns into the page message or validation error.
  - A query handler returns a `Results/` object, an enum, a scalar or null, never an Eloquent model.
  - Entry points only validate input, handle HTTP or console concerns (session, prompts, redirects) and dispatch. They never reach data another way, and that includes the helpers `auth()`, `app()` and `request()`.
  - `tests/Unit/ArchitectureTest.php` enforces the rule.
- **Repositories for all data access:** every model query and write in `app/` goes through a concrete class in `app/Repositories/` (one per model, e.g. `ProductRepository`).
  - Repositories are injected only into handlers. Query handlers call only reading methods.
  - Entry points, service providers and validation rules never query models directly.
  - The `unique`/`exists` validation rules and other queries that Laravel runs internally are exempt.
  - Tests may keep using factories and model assertions.
- **Never create interfaces for repositories.** Use concrete classes only. The `Command` and `Query` markers in `app/Bus` are the only interfaces of the architecture.

## Stack

Laravel 12 + Inertia 2 + Vue 3 with TypeScript, from the official Laravel Vue starter kit, with Tailwind, Pest 4 and a PHP 8.4 platform pin. The full stack and commands are in `openspec/config.yaml` (`context:` block).