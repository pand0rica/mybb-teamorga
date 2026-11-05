# Changelog

All notable changes to Teamorga are documented here.

---

## [Unreleased]

### Changed
- **Front end fully translatable.** All 33 templates and every `redirect()`/`error()` message
  now use language strings (English and German); the language file is loaded once per
  request in every hook that renders output (`teamorga_load_lang()`). Previously the forum
  UI was hard-coded German and the English language file only covered alerts.
- **Action buttons follow the MyBB theme** (dev kit `debugging.md` #22): every action is an
  `<input type="submit" class="button">` – navigation (add to Teamorga, edit topic, edit
  suggestion, cancel) via GET forms, state changes via POST forms. The plugin no longer
  styles its own buttons (`.teamorga-btn*` rules removed); the “More time” toggle is a
  plain JavaScript button. Participation and time-proposal buttons post
  `teamorga_status_<status>` / `proposal_accept|proposal_decline`; the old field names are
  still accepted.
- Inline styles of the suggestion field moved into the stylesheet.
- German label “Owner” → “Verantwortlich”, gender-neutral messages.

**Update note:** deactivate and reactivate the plugin so the new templates and stylesheet are
installed.
- New installations default *Groups* and *Editor Groups* to Super Moderators, Administrators and Moderators (3, 4, 6). **Upgrade note:** if you left *Forums* or *Groups* empty to mean “all”, set them to *All* in the settings after updating.

### Fixed
- **Direct `$mybb->input[...]` access.** All 15 reads of `tid`, `ttid`, `fid`, `sug_id`,
  `kick_uid` and `proposal_uid` now use `$mybb->get_input(..., MyBB::INPUT_INT)`.
- **`global $lang` declared mid-function** in `teamorga_forumdisplay()`; moved to the
  top. The language file is now loaded there, so the priority label no longer falls back
  to the raw key.
- **Hard-coded German deadline labels** in the forum list (“überfällig”, “Heute!”,
  “in X Tagen”) moved to the language files (`teamorga_fd_*`, en + de).
- Integer columns in the stylesheet queries of `teamorga_activate()` are no longer quoted.
- Empty *Forums*, *Groups* and *Editor Groups* settings meant “all” in the code although the setting descriptions say “-1 for all”. On a fresh install (all three empty) every member therefore saw the Teamorga fields in every forum, could edit every suggestion, and every user became a participant of new topics. Now MyBB's meaning applies: `-1` (*All*) = all, empty (*None*) = none – no forum, no group (only the team account), nobody may edit other people's suggestions, only the organizer is added as participant.

### Changed
- German label “Teilnehmer” → “Teilnehmende”.
- Added `.claudeignore`. README: install step, optional MyAlerts integration and the
  “Änderung erwünscht” vote are now documented correctly.

## [1.2.0] – 2026-04-11

### Added
- Forumdisplay: colored priority badge, equivalent to the showthread panel
- Forumdisplay: status dot repositioned to the left of the priority stripe
- Forumdisplay / showthread: localized priority label (uses language file)

### Fixed
- Inactive topics are no longer marked as "overdue" in forumdisplay
- Declining a "need more time" proposal now correctly removes the user from all active suggestions
- Inactive suggestions no longer trigger CTA banners; pending participant rows are cleaned up on deactivation and re-created on reactivation
- Suggestions whose linked post no longer exists are auto-deactivated on the next showthread load
- Suggestion edit form now stretches to full width inside the suggestion box
- Status dot in forumdisplay no longer renders on its own line (changed to `inline-block`)

### Security
- `kick_participant` and `update_ownership` now validate that the submitted `ttid` belongs to the current thread (prevents cross-thread manipulation)
- Integer values in SQL queries are no longer wrapped in single quotes (consistent with MyBB conventions)
- `memo_cutoff` date value is now passed through `$db->escape_string()` before use in SQL

---

## [1.1.0] – 2025

### Fixed
- Missing `owner` column in database schema
- `deadline` column now stored as VARCHAR (was DATE, broke empty values)
- `teamorga_is_installed()` used wrong field name
- CSS stylesheet insert bug in `teamorga_activate()`
- Hardcoded table prefix in `teamorga_index()`
- `teamorga_newreply()` always-truthy check

### Added
- `verify_post_check()` CSRF protection on all POST handlers
- Postbit and newreply template injections
- Participant setup when adding an existing thread retroactively

### Changed
- Helper functions renamed with `teamorga_` prefix
- Templates moved to `inc/plugins/teamorga/templates/` subdirectory
- TemplateLoader updated to upsert existing templates

---

## [1.0.0]

- Initial release
