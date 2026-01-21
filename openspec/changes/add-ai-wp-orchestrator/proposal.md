# Change: AI orchestration for WordPress builder

## Why
We want an AI-assisted workflow that can create and modify WordPress sites (pages, blocks, and styles) from natural-language prompts, similar to WordPress.com's AI builder, while keeping WordPress as the single source of truth and using drafts and undo for safety.

## What Changes
- Introduce an AI orchestration layer that:
  - Accepts a target WordPress URL and administrative credentials (e.g., Application Passwords).
  - Accepts user prompts describing desired site changes.
  - Translates prompts into a constrained JSON command schema using the **DeepSeek** LLM.
  - Executes those commands directly against the provided WordPress instance via the REST API and Gutenberg block model.
- Treat WordPress as the system of record:
  - All content and layout changes are stored as Gutenberg blocks in posts/pages.
  - Global styles are updated via Global Styles (theme.json-equivalent) APIs.
- Maintain session-level state:
  - Track a sequence of AI-applied operations per "editing session" within WordPress itself (via Custom Post Type).
  - Support undo/rollback by leveraging WordPress revisions and Global Style backups.
- Keep changes safe by default:
  - Apply AI changes to draft posts/pages or preview revisions.
  - Only persist to live content when the user explicitly confirms (e.g., Save/Publish).

## Impact
- Affected specs:
  - `ai-wordpress-orchestrator` (new capability)
- Affected code (conceptual modules in this repo):
  - **DeepSeek** LLM client and prompt/response handling.
  - Orchestration engine that maps intents → WordPress operations (REST + block JSON).
  - Session/history model stored in WordPress (CPT).
  - Integration with any configurable WordPress instance (auth, endpoints).

## Open Questions
- How should we handle media assets referenced in prompts (DALL-E integration or local uploads)?
- Resolved: The initial user-facing surface will be a **Web UI** with a simple prompt interface.
