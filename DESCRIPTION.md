# Supervisor Manager

Supervisor Manager is a Plesk extension for managing Supervisor programs from the Plesk UI.

It gives administrators a central list of configured programs and allows customers and resellers to manage only the programs assigned to their own domains. Users can view status, start, stop, restart, and tail recent log output for assigned Supervisor processes.

Administrators can enter the command a process should run. The extension generates the Supervisor config file, reloads Supervisor, and shows the generated path in the UI.

## Requirements

- Linux server with Plesk Obsidian or Plesk Onyx 17.8+
- Supervisor installed and configured, or a supported Linux OS for automatic install
- `supervisorctl` available in `/usr/bin`, `/usr/local/bin`, or `/bin`
- Plesk administrator access for installing the extension and assigning programs

## Security Model

Administrators can add and edit program assignments. Customers and resellers can only operate programs mapped to domains they own or can access in Plesk.

The extension does not create Supervisor configuration files automatically. Configure Supervisor programs on the server first, then register them in this extension and assign them to a Plesk domain.
