# JS service layer and dialogs

Read this before adding an AJAX service call (PHP service + the service map in
`lpm-scripts/lightning.js`) or building a dialog. Split out of `AGENTS.md`; the
rules are unchanged.

## Service layer (AJAX)

- Service layer (AJAX): JS calls `srv.<service>.<method>(args, onResult)` — `BaseService` is defined in `lpm-scripts/lightning.js`, per-screen bindings live in their own JS (e.g. `srv.admin` in `lpm-scripts/admin.js`). Requests hit `lpm-libs/flash2php/gateway.php` and dispatch to a PHP service extending `LPMBaseService` that returns `$this->answer()` / `$this->error('msg')`; the client sees `res.success` and `res.error`. Admin-only services override `beforeFilter()` to also require `checkRole(User::ROLE_ADMIN)` (see `lpm-core/services/AdminService.php`). **There is NO dynamic dispatch: every `srv.<service>.<method>` must be declared as an explicit wrapper in the service map in `lightning.js` — `method: function (args…, onResult) { this.s._('method'); }` (`_` reflects on the wrapper's own `arguments` via `arguments.callee.caller`, pops the last as `onResult`, sends the rest). Calling a PHP service method that has no JS wrapper throws `TypeError` before any request. File uploads use `this.s.callWithFiles('method', [args], files, onResult)` instead.**

## Dialogs and modals

- For dialogs/modals, prefer the `lpm.dialog` wrapper in `lpm-scripts/lightning.js`: `lpm.dialog.show({title, text|content, primaryBtn, onPrimary, secondaryBtn, onSecondary, ...})` and `lpm.dialog.confirm({...})`. It clones the base `#dynamicModal` template in `lpm-themes/default/page.html`. Do not add jQuery UI dialogs or hand-rolled modals. For a RICH/interactive dialog (inputs, live results): store the body markup in a hidden `<div id="…" style="display:none">` in the template and open with `lpm.dialog.show({content: tpl.innerHTML, primaryBtn: null, secondaryBtn: 'Закрыть'})`; wire interactions with delegated `document` handlers (content is added/removed dynamically) and scope all field lookups to the active `.modal.show` — the hidden source template keeps duplicate IDs in the DOM, so a global `$('#field')` reads the hidden copy. Show validation errors INLINE inside the dialog body (a hidden alert you toggle) — never call `srv.err()` from inside an open dialog, it opens a second `lpm.dialog` and the queue nests backdrops/focus traps. `lpm.dialog` serializes modals (one at a time), so `.modal.show` unambiguously identifies the current dialog. Precedent: `lpm-scripts/issues-export-to-excel.js`, `lpm-scripts/popups/add-issue-link.js`.
