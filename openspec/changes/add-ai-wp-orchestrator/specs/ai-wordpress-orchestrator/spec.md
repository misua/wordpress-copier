## ADDED Requirements

### Requirement: AI-Driven WordPress Orchestration (via DeepSeek)

The system SHALL use **DeepSeek** to translate user prompts into structured operations applied directly to a target WordPress URL via administrative credentials.

#### Scenario: Insert a block pattern from a description
- **WHEN** a user submits a prompt that describes adding a layout section (for example, "add a three-column features section to the about page")
- **THEN** the system SHALL identify the most relevant Block Pattern and insert it via the WordPress REST API.
- **AND** the change SHALL be stored as a draft.

#### Scenario: Update global styles from a palette description
- **WHEN** a user submits a prompt that describes style changes (for example, "use a green primary color palette and a modern sans-serif font")
- **THEN** the system SHALL create a snapshot of the current Global Styles.
- **AND** the system SHALL update the site's Global Styles configuration via WordPress APIs.
- **AND** the changes SHALL be visible in a site preview without immediately affecting the published site.

#### Scenario: Apply changes via Plan-Preview-Apply workflow
- **WHEN** a user submits a prompt
- **THEN** the system SHALL generate a structured "Change Plan".
- **AND** the system SHALL NOT apply changes until the user provides explicit confirmation.

### Requirement: WordPress-Native Session and Undo

The system SHALL store all AI orchestration metadata within the target WordPress instance itself.

#### Scenario: Store session history in WordPress
- **WHEN** an AI prompt is executed
- **THEN** the system SHALL record the prompt, the generated commands, and the resulting WordPress IDs (posts, revisions, media) in a hidden Custom Post Type (`_ai_session_log`).

#### Scenario: Undo the last AI-applied change
- **WHEN** a user requests to undo the last AI-applied change
- **THEN** the system SHALL retrieve the last entry from the `_ai_session_log` on the target site.
- **AND** the system SHALL restore affected content using stored snapshots or WordPress revisions.

