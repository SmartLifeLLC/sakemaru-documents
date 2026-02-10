## Skills
A skill is a set of local instructions to follow that is stored in a `SKILL.md` file. Below is the list of skills that can be used. Each entry includes a name, description, and file path so you can open the source for full instructions when using a specific skill.
### Available skills
- architect: Define architecture decisions before coding. Use when Codex needs to clarify data flow, component responsibilities, boundaries, state changes, and failure modes to keep behavior predictable and contracts explicit. (file: /Users/jungsinyu/.codex/skills/architect/SKILL.md)
- diff-writer: Generate minimal, compatibility-safe patches by outputting only required modifications. Use when Codex should avoid unchanged context, formatting-only edits, and unnecessary renames. (file: /Users/jungsinyu/.codex/skills/diff-writer/SKILL.md)
- migration-guard: Evaluate database schema migration safety and operational risk before execution. Use when Codex must assess locking, rewrite costs, index impact, backward compatibility, and suggest safer alternatives for risky changes. (file: /Users/jungsinyu/.codex/skills/migration-guard/SKILL.md)
- planner: Plan engineering work as ordered atomic steps without implementation code. Use when Codex should decompose a task into independently testable, reversible, minimal-impact actions that include verification methods, rollback strategy, and risk level for each step. (file: /Users/jungsinyu/.codex/skills/planner/SKILL.md)
- refactorer: Perform behavior-preserving refactoring with the smallest safe change set. Use when Codex must infer original intent, map dependencies and side effects, and fix root causes without rewriting whole modules. (file: /Users/jungsinyu/.codex/skills/refactorer/SKILL.md)
- runtime-debugger: Diagnose production/runtime failures from logs and errors using a causal-chain approach. Use when Codex must identify failure layer, reconstruct timeline, and propose the most probable root cause without generic advice. (file: /Users/jungsinyu/.codex/skills/runtime-debugger/SKILL.md)
- verifier: Verify implementation correctness before claiming completion. Use when Codex should systematically check runtime safety, edge cases, boundary behavior, concurrency concerns, and data consistency, and explicitly state uncertainty when confidence is incomplete. (file: /Users/jungsinyu/.codex/skills/verifier/SKILL.md)
- workflow-guard: Enforce a fixed, safety-first engineering workflow before and during implementation. Use when Codex must avoid jumping straight to coding, surface missing assumptions, ask clarifying questions when needed, design first, implement minimal safe changes, verify side effects, and preserve backward compatibility. (file: /Users/jungsinyu/.codex/skills/workflow-guard/SKILL.md)

- sakemaru-architect: Define architecture decisions before coding. Use when data flow, boundaries, and failure modes must be explicit. (file: /Users/jungsinyu/.codex/skills/sakemaru-architect/SKILL.md)
- sakemaru-diff-writer: Generate minimal, compatibility-safe patches. Use when only required modifications should be output. (file: /Users/jungsinyu/.codex/skills/sakemaru-diff-writer/SKILL.md)
- sakemaru-migration-guard: Evaluate migration safety and operational risk. Use when schema changes may lock or rewrite large tables. (file: /Users/jungsinyu/.codex/skills/sakemaru-migration-guard/SKILL.md)
- sakemaru-planner: Plan engineering work as atomic, testable, reversible steps. Use when implementation should be deferred until a clear plan exists. (file: /Users/jungsinyu/.codex/skills/sakemaru-planner/SKILL.md)
- sakemaru-refactorer: Perform behavior-preserving refactoring with minimal safe change. Use when you must fix root cause without rewrites. (file: /Users/jungsinyu/.codex/skills/sakemaru-refactorer/SKILL.md)
- sakemaru-runtime-debugger: Diagnose runtime failures by causal chain. Use when logs/errors exist and root cause is unclear. (file: /Users/jungsinyu/.codex/skills/sakemaru-runtime-debugger/SKILL.md)
- sakemaru-verifier: Verify correctness before claiming completion. Use when runtime safety and data consistency matter. (file: /Users/jungsinyu/.codex/skills/sakemaru-verifier/SKILL.md)
- sakemaru-workflow: Enforce a fixed safety-first workflow before coding. Use when requirements are unclear or risks are high. (file: /Users/jungsinyu/.codex/skills/sakemaru-workflow/SKILL.md)

- skill-creator: Guide for creating effective skills. This skill should be used when users want to create a new skill (or update an existing skill) that extends Codex's capabilities with specialized knowledge, workflows, or tool integrations. (file: /Users/jungsinyu/.codex/skills/.system/skill-creator/SKILL.md)
- skill-installer: Install Codex skills into $CODEX_HOME/skills from a curated list or a GitHub repo path. Use when a user asks to list installable skills, install a curated skill, or install a skill from another repo (including private repos). (file: /Users/jungsinyu/.codex/skills/.system/skill-installer/SKILL.md)
### How to use skills
- Discovery: The list above is the skills available in this session (name + description + file path). Skill bodies live on disk at the listed paths.
- Trigger rules: If the user names a skill (with `$SkillName` or plain text) OR the task clearly matches a skill's description shown above, you must use that skill for that turn. Multiple mentions mean use them all. Do not carry skills across turns unless re-mentioned.
- Missing/blocked: If a named skill isn't in the list or the path can't be read, say so briefly and continue with the best fallback.
- How to use a skill (progressive disclosure):
  1) After deciding to use a skill, open its `SKILL.md`. Read only enough to follow the workflow.
  2) When `SKILL.md` references relative paths (e.g., `scripts/foo.py`), resolve them relative to the skill directory listed above first, and only consider other paths if needed.
  3) If `SKILL.md` points to extra folders such as `references/`, load only the specific files needed for the request; don't bulk-load everything.
  4) If `scripts/` exist, prefer running or patching them instead of retyping large code blocks.
  5) If `assets/` or templates exist, reuse them instead of recreating from scratch.
- Coordination and sequencing:
  - If multiple skills apply, choose the minimal set that covers the request and state the order you'll use them.
  - Announce which skill(s) you're using and why (one short line). If you skip an obvious skill, say why.
- Context hygiene:
  - Keep context small: summarize long sections instead of pasting them; only load extra files when needed.
  - Avoid deep reference-chasing: prefer opening only files directly linked from `SKILL.md` unless you're blocked.
  - When variants exist (frameworks, providers, domains), pick only the relevant reference file(s) and note that choice.
- Safety and fallback: If a skill can't be applied cleanly (missing files, unclear instructions), state the issue, pick the next-best approach, and continue.
