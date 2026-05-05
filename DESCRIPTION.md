# Supervisor Manager

Supervisor Manager is a Plesk extension for managing Supervisor programs from the Plesk UI.

It gives administrators a central list of configured programs and allows customers and resellers to manage only the programs assigned to domains they can access. Reseller and customer access can be enabled or limited from Plesk service plans and subscriptions.

Authorized users can enter the command a process should run. The extension validates the selected domain, locks the project root to that domain area, generates the Supervisor config file, reloads Supervisor, and shows the generated paths in the UI. Grant command management only to subscriptions you trust to run background commands.

## Requirements

- Linux server with Plesk Obsidian or Plesk Onyx 17.8+
- Supervisor installed and configured, or a supported Linux OS for automatic install
- `supervisorctl` available in `/usr/bin`, `/usr/local/bin`, or `/bin`
- Plesk administrator access for installing the extension and enabling plan permissions

## Security Model

Administrators can add and edit all program assignments. Customers and resellers can only operate programs mapped to domains they own or can access in Plesk, and only when the matching Supervisor Manager service plan permissions are enabled.

Version 1.0.3 improves customer and reseller permission detection for impersonated sessions, reseller-owned customers, and service plan changes that need subscription sync.

The extension enforces exact domain IDs on every action and keeps generated project roots inside the selected domain area. The privileged helper also rejects unsafe config paths, log paths, root-owned processes, and project roots outside the Plesk vhost area.
