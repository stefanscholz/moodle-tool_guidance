# tool_guidance — Guidance activity chooser

Static front-end prototype: a question-and-answer **decision tree** that helps
teachers decide what to do in a new, empty Moodle course by suggesting activity
presets with sample configuration.

> **Status: static interface only.** The decision tree, presets and the
> recommendation are hardcoded placeholders. The real decision logic and
> content backend are built by a separate team and will be wired in later.
> Target: **Moodle 5.2** (PHP 8.2+).

The chooser lives at
`admin/tool/guidance/chooser.php?courseid=<id>&node=<nodeid>`. The teacher
answers a few questions and is shown suggested activity presets. The tree comes
from the static `tool_guidance\local\tree_provider` class — the swap-point for
the future content backend.

The chooser is **server-rendered** (each answer is a normal link with the node
id in the URL) with an AMD module (`tool_guidance/chooser`) layering no-reload
stepping on top. It works fully without JavaScript.

It pairs with the companion [`block_guidance`](https://github.com/stefanscholz/moodle-block_guidance)
next-step block, whose call-to-action deep-links into this chooser.

## Installation

Clone (or copy) this repository into `admin/tool/guidance` inside your Moodle:

```sh
# From your Moodle root:
git clone git@github.com:stefanscholz/moodle-tool_guidance.git admin/tool/guidance
```

Then visit **Site administration → Notifications** to install the plugin.

## Admin: guidance graph editor

Site administrators (capability `tool/guidance:manage`) can author the
decision trees themselves at **Site administration → Plugins → Admin tools →
Guidance graphs** (`admin/tool/guidance/index.php`). Each graph is a set of
question and leaf nodes connected by answers, edited on a drag-and-drop canvas
(`edit.php`) backed by AJAX external functions (`classes/external/`) and
stored in `tool_guidance_graph`/`_node`/`_link` tables (`db/install.xml`).

This is the intended real source for the tree the chooser walks — see
"Wiring up the backend later" below.

## Wiring up the backend later

- Replace `tool_guidance\local\tree_provider` (return real `node`/`preset`
  objects, e.g. behind a `tree_source` interface) — likely backed by the
  graph editor's storage described above.
- Implement real activity creation behind the result-card "Use this template"
  action (currently a placeholder that returns to the course).

Everything else — exporters, renderables, templates, AMD — depends only on the
`node` and `preset` shapes, so it stays unchanged.
