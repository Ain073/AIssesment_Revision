# Code organization

The application keeps role-specific authorization and routes in controllers, shared calculations in `app/Support`, persistence workflows in `app/Services`, and presentation in Blade views.

## Shared behavior

- `PortalNavigation` supplies the menu for every role, including profile pages. Add or change a menu entry there rather than copying a menu into another view or controller.
- `ReportAnalytics` supplies report scores and submission eligibility. `ReportItemAnalysis` adds per-question results without maintaining a second eligibility rule.
- `ReportMetadata` supplies common report header data. Controllers retain their existing selection and student-count scopes.
- `config/reports.php` contains paper options and report column labels. The shared header and table partials live in `resources/views/partials/reports`. Browser-specific print layouts remain separate.
- Assessment details, question/item operations, and publishing have separate controllers. Their public URLs, route names, and ownership checks are unchanged.

## Browser assets

Runtime JavaScript and CSS are served from `public/js` and `public/css`. Page styles mirror their Blade view paths under `public/css/pages`; `<x-page-style>` adds their versioned URLs. Small dynamic print settings stay in Blade.

The portal layout loads shared browser modules once. `portal.js` owns AJAX navigation and form submission. Reports, class forms, assessment filters, tabs, feedback, polling, and dashboard controls have their own modules. Features initialize through their `window.initialize…` functions after page or fragment replacement; event handlers use delegation where controls are replaced by AJAX.

Report editing, the question builder, and student assessment behavior use ordinary JavaScript files. Their Blade partials supply escaped JSON configuration through `application/json` script elements. Keep PHP expressions out of executable JavaScript files.

AJAX navigation waits for a new page's stylesheets before replacing its content. A failed or superseded response keeps the current page's styles.

The existing Vite/Tailwind scaffold and build commands are retained for compatibility with the current development and Docker workflows. They do not supply the portal's runtime assets. A future bundler migration must update layout loading, page scripts, and AJAX stylesheet handling together.

## Tests

Feature tests are grouped by behavior: account updates, directory/search, creation, availability, imports, colleges, designations, audit display, and historical audit recovery. `tests/Support/AccountFeatureTestCase.php` supplies the shared isolated SQLite account fixture; it contains no test methods.

Run the PHP suite with `php artisan test --compact`. JavaScript tests use Node's built-in test runner; in PowerShell:

```powershell
node --test @(Get-ChildItem tests/JavaScript -File | Where-Object { $_.Name -match '\.test\.(mjs|js)$' } | ForEach-Object FullName)
```

After changing views or route actions, also verify Blade compilation and route caching. Local automated checks do not establish deployment or live database behavior.
