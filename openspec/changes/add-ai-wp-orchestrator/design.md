## Context

We want an AI orchestration layer that acts as a "commander" over a specified WordPress site via a **Web-based user interface**. The orchestrator should:
- Accept a target WordPress URL and administrative credentials.
- Provide a simple **text input** for natural-language prompts.
- Use **DeepSeek** to translate them into a constrained set of JSON commands.
- Execute those commands against the target WordPress instance using the REST API and Gutenberg's block model.
- Maintain a browser-based **Plan-Preview-Apply** flow.
- Keep changes safe via drafts, previews, and revisions.

This change is cross-cutting: it touches external WordPress infrastructure, LLM providers, and internal orchestration logic.

## Goals / Non-Goals

- Goals:
  - Target any WordPress site via URL and Auth.
  - Prioritize **Block Patterns** and **Global Styles** for high-fidelity modifications.
  - Maintain session history and undo states directly in WordPress (via Custom Post Type).
  - Implement a **Plan-Preview-Apply** workflow for user safety.

- Non-Goals (initially):
  - Building a full multi-tenant SaaS platform.
  - Generating arbitrary PHP or plugin code.
  - Supporting every possible Gutenberg block and pattern from day one.
  - Replacing the standard WordPress editor UI.

## Decisions

- Decision: **Direct Target Execution**
  - The orchestrator connects to any WordPress instance provided via URL.
  - It requires Admin credentials (Application Password recommended).

- Decision: **DeepSeek for LLM Reasoning**
  - We use DeepSeek's API for its superior reasoning and JSON consistency.
  - The client is OpenAI-compatible, targeting `api.deepseek.com`.

- Decision: **WordPress as source of truth**
  - All content, block structures, and styles remain stored in WordPress (posts, Global Styles).
  - AI session metadata (history, snapshots) is stored in WordPress via a Custom Post Type (`_ai_session_log`).

- Decision: **Pattern-First Command Schema**
  - To ensure layout validity, the LLM prefers inserting registered **Block Patterns** over raw block construction.
  - Commands include:
    - `insert_pattern { pattern_slug, target_post_id, context }`
    - `update_global_styles { colors, fonts, spacing }`
    - `create_page { title, blocks[] }`
    - `upload_media { description, usage_context }`
  - The orchestrator validates and executes these commands against the WordPress REST API.

- Decision: **Safety via drafts and Plan-Preview-Apply**
  - All AI changes are initially applied to draft posts or preview revisions.
  - The system generates a "Change Summary" (Plan) before execution, which the user must approve.
  - Publishing remains a separate, explicit action outside the LLM's direct control.

- Decision: **REST-only integration to WordPress**
  - No direct database writes from the orchestrator.
  - Use standard WordPress REST endpoints (posts, revisions, Global Styles, media) for all changes.

## Risks / Trade-offs

- Risk: LLM emits invalid or unsafe command JSON.
  - Mitigation: Strict schema validation; reject malformed commands and surface clear errors.
  - Mitigation: Add a dry-run mode that only logs the commands that would be executed.

- Risk: Complex layouts are hard to express within a small command schema.
  - Mitigation: Start with a narrow set of supported operations (new page, add section, update palette) and expand iteratively.

- Risk: Undo semantics become confusing when multiple prompts touch the same resources.
  - Mitigation: Define undo at the prompt level, logging which posts/pages and revisions were affected for each prompt.

## Migration Plan

- Phase 1: Prototype against a target WordPress instance.
  - Implement the DeepSeek client and command schema.
  - Implement the "Plan-Preview-Apply" workflow.
  - Validate flows for inserting patterns, updating styles, and undoing changes.

- Phase 2: Integrate with existing workflows.
  - Decide where in the current "wordpress-copier" flows the orchestrator should plug in (if at all).
  - Add configuration to point at different WordPress environments.

- Phase 3: Harden and expand.
  - Add more command types and block support as needed.
  - Tighten observability, error handling, and operational runbooks for production usage.

## Open Questions

- Which WordPress environments and URLs should be supported in this project by default?
- What is the minimum command set we need to support for the first iteration?
- Do we need multi-user/multi-tenant session isolation at this stage, or is a single user/site model sufficient?
