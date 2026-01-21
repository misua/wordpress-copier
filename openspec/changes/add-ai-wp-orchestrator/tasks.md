## 1. Discovery & Architecture
- [x] 1.1 Finalize the connection interface for target WordPress sites (URL, Username, Application Password).
- [x] 1.2 Enumerate required WordPress REST endpoints (posts/pages, revisions, Global Styles, media upload, CPTs).
- [x] 1.3 Audit available Gutenberg Block Patterns on target sites to build a pattern library for the LLM.

## 2. LLM & Command Schema (DeepSeek)
- [x] 2.1 Implement an OpenAI-compatible client targeting DeepSeek (`api.deepseek.com`).
- [x] 2.2 Design a constrained JSON command schema prioritizing `insert_pattern` and `update_global_styles`.
- [x] 2.3 Define the initial system prompt optimized for DeepSeek reasoning and JSON consistency.
- [x] 2.4 Add validation for LLM-generated commands against the JSON schema.

## 3. Orchestration Engine (Plan-Preview-Apply)
- [x] 3.1 Implement a "Plan" phase that generates a human-readable summary of proposed changes.
- [x] 3.2 Implement the execution logic that maps JSON commands to WordPress REST operations.
- [x] 3.3 Ensure all content changes are applied to draft posts or preview revisions.
- [x] 3.4 Implement Global Styles snapshots to allow reliable rollbacks of aesthetic changes.

## 4. Session & Undo Model (WordPress-Native)
- [x] 4.1 Register a Custom Post Type (`_ai_session_log`) in WordPress to store session history and snapshots.
- [x] 4.2 Store pre-change snapshots and revision IDs within the `_ai_session_log` entries.
- [x] 4.3 Implement the "Undo" command by restoring content from the snapshots stored in the target site.

## 5. Interaction Surface (Web UI)
- [x] 5.1 Implement a web interface with a central prompt textbox for user input.
- [x] 5.2 Implement the "Preview" state in the UI, displaying the AI's plan and a confirmation button.
- [x] 5.3 Implement real-time status updates and links to drafts/previews upon successful application.
- [x] 5.4 Provide an "Undo" button in the UI to trigger the latest session rollback.

## 6. Validation & Testing
- [x] 6.1 Add unit tests for the command schema validation and command→REST mapping logic.
- [x] 6.2 Add integration tests against a disposable/dev WordPress instance for:
  - Creating a new page from a description.
  - Updating global styles from a palette description.
  - Applying block-level edits to an existing page.
  - Undoing the last AI-applied change.
- [x] 6.3 Document configuration and operational runbooks (how to point at a WordPress instance, how to enable/disable the feature, and safety defaults).
