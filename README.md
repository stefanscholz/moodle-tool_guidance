# tool_guidance — Guidance activity chooser + suggestion engine

The "brain" of the **Guidance** project. Two things live here:

1. **A deterministic suggestion engine.** It builds a *profile* of a course (structure,
   pedagogical purposes, lifecycle stage, optional engagement facts) and evaluates an
   ordered, admin-editable **rule table** (100 seeded rules) to pick the single most
   relevant next activity. First matching rule (by precedence) whose activity is installed
   and not dismissed wins. Course-wide dismissal with a cooldown; optional AI *re-ranking*
   of the matched candidates (works fully without AI). This engine backs the companion
   [`block_guidance`](../../../blocks/guidance) next-step block.

2. **A guided activity chooser.** A question-and-answer decision tree
   (`admin/tool/guidance/chooser.php`) that suggests activity **presets** with sample
   configuration, and hooks a "Help me choose…" entry into the activity-chooser ("+") menu.

## Deep-linking

`chooser.php?courseid=<id>&modname=<mod>` lands directly on the presets for a given
activity — this is the target the block's "Set this up" call-to-action uses. Activities
with bespoke presets (quiz/assign/forum) show them; others get a synthesised generic
landing until a template is authored.

## Managing rules

**Site administration → Plugins → Admin tools → Guidance activity chooser → Manage rules.**
Rules are ordered by precedence and edited without code (condition DSL, suggested activity,
rationale, pre-config). Settings there also control AI re-ranking, the dismissal cooldown,
and whether the costlier engagement facts are computed.

## Still a placeholder

Creating an activity from a preset ("Use this template") returns to the course for now —
real instance creation is the next piece of work. The interactive Q&A tree
(`local\tree_provider`) is still static; the automatic block suggestion is engine-driven.
