# Watch Mode

Watch mode turns a Behat run into an interactive development loop: each step is numbered, errors pause the run, and you can restart from any line without re-executing preceding setup. Useful for authoring new feature files against a real remote app, where replaying all setup steps for each iteration is slow.

## Running

```bash
php bin/behat --watch -- path/to/feature.feature
```

The `--watch` flag is exclusive to the Oro Behat extension and takes no arguments. Combine with `--skip-isolators` when targeting a remote app:

```bash
php bin/behat --skip-isolators --watch -- path/to/feature.feature
```

## Behavior

1. Before each step executes, Behat prints the step's **line number** in the feature file:

   ```
   #24  Given I go to "admin"
   #25  And I fill form with:
   #26    | Username | admin |
   ```

2. When a step fails, Behat does **not** abort. Instead it prints the failure and drops into an interactive prompt:

   ```
   Press ENTER to continue from the current line #26, or enter the line number
   to continue (Ctrl+C to exit):
   ```

3. Three choices at the prompt:
   - **ENTER** — retry the step that just failed (after you've fixed it in the feature file or the app under test)
   - **Line number** — jump back to an earlier step and replay from there (useful when the failure's root cause was a setup step two paragraphs up)
   - **Ctrl+C** — exit watch mode entirely

4. After a successful completion the scenario **restarts from the top** — watch mode is cyclic until you Ctrl+C. This is deliberate: lets you iterate on test assertions without restarting Behat.

## Typical Workflow

1. Write a feature file draft with rough step text.
2. `php bin/behat --skip-isolators --watch -- drafts/new-flow.feature`
3. First failure — fix the step wording or locator in the feature file, save.
4. Press ENTER to re-run from the failing line. Old state from earlier steps is preserved in the browser session.
5. Walk through the scenario until the last step passes.
6. Ctrl+C to exit.

Because watch mode bypasses isolation setup on each cycle, iteration is fast — no schema drop, no fixture reload. The trade-off is that scenario state accumulates between runs, which is exactly what you want for interactive authoring but deadly for suite runs.

## Pitfalls

1. **Using watch mode in CI** — it blocks on a prompt forever. Watch mode is a local-only authoring tool.
2. **Assuming each cycle starts clean** — browser session, logged-in user, created records all persist across cycles. If earlier steps have side effects, later cycles diverge from the first run.
3. **Saving the feature file without Behat picking up changes** — Behat re-reads the feature file on each cycle, but not mid-cycle. Edit, then press ENTER.
