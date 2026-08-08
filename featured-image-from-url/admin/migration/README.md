# FIFU Migration Module

This directory contains the database migration layer for the FIFU (Featured Image from URL) plugin. It is designed for large datasets (millions of rows) and runs in small, resumable batches to avoid timeouts.

## Directory structure

- `schema/`
  - Contains SQL files used to create or update the new FIFU tables:
    - `{PREFIX}fifu_url`
    - `{PREFIX}fifu_key`
    - `{PREFIX}fifu_map`
    - `{PREFIX}fifu_alt`
    - `{PREFIX}fifu_alt_map`

- `core/`
  - Contains core migration engine classes:
    - schema manager (executes the SQL files in `schema/`)
    - migration state (progress per task)
    - migration logger
    - migration task interface
    - migration registry
    - migration runner (batch orchestration)

- `tasks/`
  - Contains concrete migration tasks, one per feature, e.g.:
    - featured image URLs and ALT texts
    - category image URLs and ALT texts

- `ui/`
  - Contains integration with external interfaces:
    - WP-CLI commands (`fifu-migrate`)
    - admin page renderer for viewing migration status

## New database schema (high level)

- `fifu_url`: stores unique URLs, optional metadata (width/height), and validation tracking columns (`is_valid`, `validation_attempts`, `validation_last_attempt`).
- `fifu_alt`: stores unique ALT texts.
- `fifu_key`: defines logical types such as `image`, `slider`, `video`, `audio`, `iframe`, `custom_video`, `finder`, `redirect`.
- `fifu_map`: links WordPress objects (posts) to keys and URL hashes using `(post_id, key_id, key_index)`.
- `fifu_alt_map`: links WordPress objects (posts) to keys and ALT hashes using `(post_id, key_id, key_index)`.

## Migration workflow (conceptual)

- schema manager creates/updates the new tables using the SQL files in `schema/`.
- each task in `tasks/` reads legacy data from `wp_postmeta` in small batches using `meta_id` ranges.
- data is normalized into `fifu_url`, `fifu_key`, and `fifu_map`.
- `Fifu_Migration_State` tracks progress for each task (`status`, `last_id`, counters).
- `Fifu_Migration_Runner` coordinates running one batch at a time.
- WP-CLI and admin UI call the runner; they do not contain business logic.

## Safety and isolation

- This module does not change existing plugin behavior by itself.
- No hooks or menu registrations are done inside this directory.
- Wiring (calling the runner, registering CLI commands, adding admin pages) is done elsewhere in the plugin.
- Migration is designed to be incremental and resumable, avoiding long-running requests and timeouts.

## Notes for contributors

- New migration features should be implemented as separate tasks in `tasks/`.
- Tasks must implement `Fifu_Migration_Task_Interface`.
- Tasks must be idempotent and safe to re-run.
- Always respect the batch limit and time limit parameters.
