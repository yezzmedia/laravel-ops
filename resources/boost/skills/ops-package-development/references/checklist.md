# Ops Package Checklist

- confirm `OpsPlatformPackage` declarations still match the real page and widget surface
- confirm `OpsPanelProvider` page list and companion plugin loading still match runtime intent
- confirm `OpsNavigationResolver` remains the single navigation projection source
- confirm diagnostics wording does not overstate optional host health-provider integration
- confirm resolver changes are covered by focused unit or feature tests
- confirm `composer test:ops` passes from `1-dev-test`
