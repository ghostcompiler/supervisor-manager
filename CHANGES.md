# 1.0.3

- Improved customer and reseller permission resolution for impersonated sessions, direct domain ownership, reseller-owned customers, and freshly synced plan limits.
- Added a guarded access/manage fallback when Plesk has not exposed custom permission values yet but the subscription has a non-zero Supervisor program limit.
- Clarified the denied-state message so existing subscriptions can be synced after plan permission changes.

# 1.0.2

- Added Plesk service plan permissions for Supervisor access, management, control actions, and log access.
- Added a service plan limit for maximum Supervisor programs per subscription.
- Enforced service plan permissions on every create, edit, delete, config generation, control, and log action.
- Added a friendly denied state when Supervisor Manager is not enabled for the account or selected domain.
- Registered Plesk custom buttons unconditionally so plan activation does not require creating a new domain before the menu appears.
- Normalized domain custom-button clicks to exact `site_id` links to avoid old domain pages falling back to Plesk's generic web view.
- Hardened the privileged helper against tampered local data by validating config paths, log paths, process users, and Plesk vhost roots.

# 1.0.1

- Added Supervisor entry to the Plesk domain Dev Tools section.
- Added exact domain/subdomain context handling with `site_id` priority.
- Hid the domain selector when adding a program from a domain page.
- Kept domain context across save, edit, logs, refresh, and process actions.

# 1.0.0

- Initial Supervisor Manager extension.
- Program listing, status refresh, start, stop, restart, and log tail.
- Administrator program assignment UI.
- Customer and reseller scoped access by assigned Plesk domain.
