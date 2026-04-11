# Ops Testing Pattern

- Use package tests as the primary proof surface.
- Keep descriptor and registry expectations in `OpsBootstrapTest`.
- Keep panel visibility, headings, sections, and detail-page behavior in feature tests.
- Keep resolver projection logic in unit tests when a change affects summaries, navigation, or integration state.
- Run `composer test:ops` from `/home/yezz/Developement/packages/1-dev-test` for targeted verification.
- Run `composer test:all` from `/home/yezz/Developement/packages/1-dev-test` before considering broad work complete.
