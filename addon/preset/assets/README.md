# Bundled demo preset backups

`presets.xml` in this directory describes the demo activity presets that are
seeded on install by `preset_manager::create_default_presets()`.

Each `<preset>` references a `<backupfile>` — a **single-activity** Moodle backup
(`.mbz`) that must live in this directory next to `presets.xml`. These binary
backups are **not** committed to the repository, so out of the box the seeding
step skips every preset (the chooser then falls back to its display-only
placeholder). Presets can also be created at any time through
*Site administration → Plugins → Admin tools → Manage activity presets*.

## Producing the `.mbz` files

For each preset, back up a single activity (not a whole course) and drop the
resulting file here using the exact filename from `presets.xml`, e.g.
`quiz_diagnostic.mbz`.

You can create them from the UI (activity *Backup*), or via the CLI helper
`admin/tool/guidance/addon/preset/cli/generate_demo_backups.php` if provided.
The short name in `presets.xml` must match the short name the decision tree
references in `tool_guidance\local\tree_provider`.
