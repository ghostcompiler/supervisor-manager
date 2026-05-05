# 1.0.2

- Hide customer and reseller navigation when Supervisor Manager access is not enabled for any assigned domain.
- Suppress the disabled-account warning and empty-state panel for accounts without Supervisor Manager access.

# 1.0.1

- Hardened status messages so success and error banners only come from server-side flash state, not URL parameters.
- Tightened non-admin access fallback so Supervisor Manager access still requires the explicit access permission before a resource limit can allow program management.
- Hardened config existence checks to only probe managed Supervisor config paths.
- Removed an unused duplicate privileged helper from the packaged `sbin` surface.
- Compact locked domain display on the add/edit form so it aligns with the rest of the fields.

# 1.0.0

- Initial Supervisor Manager extension release.
- Added program listing, status refresh, start, stop, restart, config generation, delete, and live log tail.
- Added administrator, reseller, and customer scoped access by assigned Plesk domain.
- Added Plesk service plan permissions and subscription limits for Supervisor Manager access.
- Added domain Dev Tools integration with exact `site_id` handling.
- Added project root locking, command validation, config path validation, log path validation, and privileged helper checks.
- Added Supervisor install detection and a polished not-installed state with only Install and Info actions.
- Added Ghost Compiler creator metadata, email, logo, GitHub repository, and profile links.
- Added an Info button on the manager page with version, creator, GitHub, and direct install command details.
- Added a Ghost Compiler footer on the manager page.
- Added GitHub Actions CI and release workflows that validate PHP, build the Plesk ZIP, and publish GitHub release assets.
- Added a rolling latest-package workflow that rebuilds `supervisor-manager.zip` on main pushes and manual requests.
- Switched the documented install URL to the stable rolling GitHub package asset.
