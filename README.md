<p align="center">
  <img src="https://assets.ghostcompiler.in/logo.png" alt="Supervisor Manager Logo" width="180">
</p>

<h1 align="center">Supervisor Manager for Plesk</h1>

<p align="center">
  Manage Supervisor processes from Plesk with admin, reseller, and customer scoped access.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Plesk-Extension-52A8E8?style=for-the-badge&logo=plesk&logoColor=white" alt="Plesk Extension">
  <img src="https://img.shields.io/badge/Supervisor-Process%20Manager-0F172A?style=for-the-badge" alt="Supervisor">
  <img src="https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/Ubuntu-24.04-E95420?style=for-the-badge&logo=ubuntu&logoColor=white" alt="Ubuntu">
  <img src="https://img.shields.io/badge/Live%20Logs-Ready-16A34A?style=for-the-badge" alt="Live Logs">
  <img src="https://img.shields.io/badge/Domain%20Scoped-Secure-2563EB?style=for-the-badge" alt="Domain Scoped">
</p>

<p align="center">
  <a href="https://github.com/ghostcompiler/supervisor-manager/actions/workflows/ci.yml">
    <img src="https://github.com/ghostcompiler/supervisor-manager/actions/workflows/ci.yml/badge.svg" alt="CI">
  </a>
  <a href="https://github.com/ghostcompiler/supervisor-manager/actions/workflows/release.yml">
    <img src="https://github.com/ghostcompiler/supervisor-manager/actions/workflows/release.yml/badge.svg" alt="Release">
  </a>
  <a href="https://github.com/ghostcompiler/supervisor-manager/actions/workflows/package-latest.yml">
    <img src="https://github.com/ghostcompiler/supervisor-manager/actions/workflows/package-latest.yml/badge.svg" alt="Latest Package">
  </a>
  <img src="https://img.shields.io/badge/Creator-Ghost%20Compiler-111827?style=flat-square" alt="Creator">
  <img src="https://img.shields.io/badge/Status-Production%20Ready-22C55E?style=flat-square" alt="Status">
  <img src="https://img.shields.io/badge/Access-Admin%20%7C%20Reseller%20%7C%20Customer-blue?style=flat-square" alt="Access">
  <img src="https://img.shields.io/badge/Config-/etc/supervisor/conf.d-lightgrey?style=flat-square" alt="Config Path">
</p>

---

## Overview

Supervisor Manager is a Plesk extension for creating and managing Supervisor programs directly from the Plesk interface.

It is built for hosting panels where admins need to run background commands for individual domains while keeping customers locked to their own domain scope. It can manage PHP workers, Node workers, queue consumers, schedulers, websocket servers, custom scripts, and other long-running services.

## Creator

- **Name:** Ghost Compiler
- **Email:** [hello@ghostcompiler.in](mailto:hello@ghostcompiler.in)
- **GitHub:** [ghostcompiler/supervisor-manager](https://github.com/ghostcompiler/supervisor-manager)
- **Profile:** [github.com/ghostcompiler](https://github.com/ghostcompiler)
- **Logo:** [assets.ghostcompiler.in/logo.png](https://assets.ghostcompiler.in/logo.png)

## Use Cases

- Run queue workers and background consumers.
- Run Node, PHP, Python, or shell-based worker processes.
- Manage websocket servers, schedulers, bots, daemons, and custom long-running commands.
- Let customers restart only the processes assigned to their domains.
- Give admins a single Plesk page for status, config generation, restart controls, and live logs.
- Keep Supervisor config files generated consistently under `/etc/supervisor/conf.d`.

## Screenshots


  <a href="docs/screenshots/dashboard.png">
    <img src="docs/screenshots/dashboard.png" alt="Supervisor Manager dashboard">
  </a>
  <a href="docs/screenshots/add-program.png">
    <img src="docs/screenshots/add-program.png" alt="Add Supervisor program">
  </a>
  <a href="docs/screenshots/live-logs.png">
    <img src="docs/screenshots/live-logs.png" alt="Live log preview">
  </a>

<details>

### 1. Dashboard

![Supervisor Manager dashboard](docs/screenshots/dashboard.png)

### 2. Add Program

![Add Supervisor program](docs/screenshots/add-program.png)

### 3. Live Logs

![Live log preview](docs/screenshots/live-logs.png)

</details>

## Features

- Admin dashboard for all managed Supervisor programs.
- Customer and reseller scoped access by assigned Plesk domain.
- Add, edit, delete, start, stop, restart, and regenerate config.
- Live log preview with pause/resume and 120/300/500 line views.
- Automatic Supervisor config generation.
- Project root locking so users cannot escape their domain area.
- Project root validation for domain-owned applications.
- Runtime PATH handling for Plesk PHP, Plesk Node.js, and system binaries.
- Generated config and log paths shown in the UI.
- One-click Supervisor install on supported Linux distributions.

## Requirements

- Plesk Onyx or Obsidian on Linux.
- PHP available to Plesk admin runtime.
- Supervisor installed, or an OS supported by the install button.
- Required runtime installed for the command you want to run, such as PHP, Node.js, Python, or another CLI binary.

Supported install detection includes:

- Ubuntu / Debian
- AlmaLinux / Rocky / RHEL / CentOS / Fedora

## Installation

Install the latest runner-built package directly from GitHub:

```sh
plesk bin extension --install-url https://github.com/ghostcompiler/supervisor-manager/releases/download/latest/supervisor-manager.zip
```

This URL points to the rolling `latest` release asset. The **Package Latest** workflow rebuilds `supervisor-manager.zip` from the current `main` branch on every push and whenever it is started manually, so the install command stays stable and does not depend on a hardcoded version number.

Pinned version installs are also available after publishing a versioned release:

```sh
plesk bin extension --install-url https://github.com/ghostcompiler/supervisor-manager/releases/download/v1.0.3/supervisor-manager-1.0.3.zip
```

Build the extension ZIP:

```sh
mkdir -p build
COPYFILE_DISABLE=1 zip -r build/supervisor-manager-1.0.3.zip meta.xml DESCRIPTION.md CHANGES.md README.md htdocs plib sbin -x '*.DS_Store' -x '__MACOSX/*'
```

Install through Plesk CLI:

```sh
plesk bin extension --install build/supervisor-manager-1.0.3.zip
```

Or install through Plesk UI:

1. Open **Plesk Admin**.
2. Go to **Extensions**.
3. Click **Upload Extension**.
4. Upload `build/supervisor-manager-1.0.3.zip`.
5. Open **Supervisor** from the Plesk sidebar.

## Version 1.0.3

Version 1.0.3 improves Supervisor runtime setup, domain PHP detection, and log handling. It adds service health diagnostics with a repair action, generates domain-scoped Supervisor program names, resolves `php` through the selected Plesk domain PHP handler, and adds copy/clear controls for live logs.

After installing:

1. Open **Supervisor** from Plesk.
2. Use **Repair Supervisor** if the service or socket needs setup.
3. Regenerate existing program configs so domain PHP paths and scoped names are written.
4. Restart affected programs from the extension.
5. Use **Copy Log** or **Clear Log** from the logs page when troubleshooting.

## How It Works

When an authorized user saves a program, the extension:

1. Validates the selected domain.
2. Locks the project root to the selected domain area.
3. Generates a Supervisor config file.
4. Writes the config to `/etc/supervisor/conf.d`.
5. Runs `supervisorctl reread` and `supervisorctl update`.
6. Shows status, config path, log path, and live logs in Plesk.

## Adding a Program

Example queue worker:

```text
Supervisor Program Name: queue-worker
Display Name: Queue Worker
Assigned Domain: example.com
Command: php artisan queue:work --sleep=3 --tries=3
Project Root: /var/www/vhosts/example.com/app
Start on boot: enabled
Restart if it exits: enabled
Enabled: enabled
```

Example Node worker:

```text
Supervisor Program Name: realtime-worker
Display Name: Realtime Worker
Assigned Domain: example.com
Command: npm run worker
Project Root: /var/www/vhosts/example.com/realtime-app
```

Example custom script:

```text
Supervisor Program Name: importer
Display Name: Product Importer
Assigned Domain: example.com
Command: /usr/bin/python3 worker.py
Project Root: /var/www/vhosts/example.com/importer
```

Set **Project Root** to the application folder where the command should run. For Laravel commands, this is usually the folder containing `artisan`. For Node.js commands, this is usually the folder containing `package.json`.

## Generated Files

Supervisor configs:

```sh
/etc/supervisor/conf.d/plesk-*.conf
```

RHEL-style fallback path:

```sh
/etc/supervisord.d/plesk-*.conf
```

Program logs:

```sh
/var/log/supervisor/plesk/*.log
```

Extension data:

```sh
/usr/local/psa/var/modules/supervisor-manager/data/programs.json
```

Privileged helper:

```sh
/usr/local/psa/admin/sbin/modules/supervisor-manager/supervisor-manager
```

## Security Model

- Admins can create, edit, delete, and regenerate all programs.
- Customers and resellers can only see programs assigned to domains they can access.
- Customer and reseller access is controlled by Plesk service plan permissions.
- If Plesk delays exposing custom access/manage permission values after a plan change, a non-zero **Maximum Supervisor programs** limit is treated as a guarded activation fallback for domains the user already owns or can access.
- Project roots are locked to the selected domain area.
- Users cannot use `../` style path escapes to reach another domain.
- Server-level writes are performed through the Plesk `sbin` helper.
- The `sbin` helper revalidates config paths, log paths, process users, and allowed project roots before touching server files.
- Managed commands run as the selected domain system user. Only grant **Manage Supervisor programs** to users who are trusted to run commands for that subscription.

## Service Plan Access

Supervisor Manager adds permissions to Plesk service plans and subscriptions. Keep them disabled by default, then enable only what each reseller or customer should be allowed to do.

Available permissions:

- **Supervisor Manager access**: allows the user to open the extension and view assigned programs.
- **Manage Supervisor programs**: allows creating, editing, deleting, and regenerating configs for enabled domains.
- **Control Supervisor programs**: allows start, stop, and restart actions.
- **View Supervisor logs**: allows opening the live log preview.

Available limit:

- **Maximum Supervisor programs**: caps how many programs can be created per subscription. Use `0` to prevent customer-created programs, a positive number for a fixed cap, or `-1` for unlimited.

Avoid unlimited process counts for shared-hosting customers unless they are trusted. Background programs can consume CPU, RAM, and ports just like commands run over SSH.

Access is checked in three layers:

1. The logged-in user must have access to the Plesk domain.
2. The domain subscription must have the matching Supervisor Manager permission enabled.
3. The posted program must belong to that exact domain ID, not a parent domain or a similar subdomain.

After changing an existing service plan, sync the affected subscriptions in Plesk. Customized or locked subscriptions may keep their old permission values until they are synced or adjusted directly.

The extension always registers its Plesk sidebar and domain buttons so service plan changes can appear without waiting for a new domain event. Security is still enforced in the controller after the button is clicked.

Domain buttons are normalized to `site_id`, because Plesk context parameters can include `dom_id` for the subscription/webspace and `site_id` for the exact domain. Supervisor Manager uses the exact domain ID for filtering and actions.

## Live Logs

The log page opens in a new tab and provides:

- Auto refresh every 2.5 seconds.
- Pause and resume.
- Last update timestamp.
- 120, 300, and 500 line views.
- Direct reading from `/var/log/supervisor/plesk/*.log`.

## Useful Commands

Check Supervisor:

```sh
supervisorctl status
```

Reread generated configs:

```sh
supervisorctl reread
supervisorctl update
```

Check generated files:

```sh
ls -lah /etc/supervisor/conf.d/
ls -lah /var/log/supervisor/plesk/
```

Install Supervisor on Ubuntu manually:

```sh
sudo DEBIAN_FRONTEND=noninteractive apt-get update
sudo DEBIAN_FRONTEND=noninteractive apt-get install -y supervisor
sudo systemctl enable --now supervisor
```

Check a runtime binary:

```sh
which php
which node
which python3
```

## Troubleshooting

### Config is saved but process is BACKOFF

Open **Logs**. The most common causes are:

- Wrong project root.
- Missing command runtime, such as PHP, Node.js, or Python.
- Missing application file, such as `artisan`, `package.json`, or the script passed to the command.
- Port already in use.
- Command exits immediately.

### Command cannot find an application file

Set **Project Root** to the folder where the command normally runs over SSH.

```sh
cd /var/www/vhosts/example.com/app
ls -lah
```

For example:

- Laravel commands usually run from the folder containing `artisan`.
- Node.js commands usually run from the folder containing `package.json`.
- Python or shell workers usually run from the folder containing the script file.

### Runtime command not found

Install the missing runtime or use the full binary path in the command. Examples:

```sh
which php
which node
which python3
```

### Port already in use

If the managed application binds to a port and the port is already busy, stop the duplicate process or change the application port.

### Save or delete does not show a status message

The extension returns to the manager page and shows a success or error message at the top. If the browser is still on the form page, refresh once and check the manager page again.

## Development

Validate PHP syntax:

```sh
find . -name '*.php' -o -name '*.phtml' | sort | xargs -n1 php -l
```

Package:

```sh
mkdir -p build
COPYFILE_DISABLE=1 zip -r build/supervisor-manager-1.0.3.zip meta.xml DESCRIPTION.md CHANGES.md README.md htdocs plib sbin -x '*.DS_Store' -x '__MACOSX/*'
```

## Release Automation

GitHub Actions handles packaging and release assets:

- `CI` runs on every push and pull request.
- `CI` validates PHP syntax, validates `meta.xml`, builds the ZIP, tests the ZIP, and uploads it as a workflow artifact.
- `Package Latest` runs on every push to `main` and can be started manually.
- `Package Latest` moves the rolling `latest` tag to the current commit and uploads `supervisor-manager.zip` to that release.
- `Release` runs when a tag like `v1.0.3` is pushed, or when started manually.
- `Release` requires the tag version to match `meta.xml`.
- `Release` uploads versioned assets like `supervisor-manager-1.0.3.zip` for pinned installs.

Refresh the rolling latest installer from the current `main` branch:

```sh
git push origin main
```

Then install the newest runner-built package:

```sh
plesk bin extension --install-url https://github.com/ghostcompiler/supervisor-manager/releases/download/latest/supervisor-manager.zip
```

Create a release:

```sh
git tag v1.0.3
git push origin v1.0.3
```

After the release workflow finishes, the pinned install command works:

```sh
plesk bin extension --install-url https://github.com/ghostcompiler/supervisor-manager/releases/download/v1.0.3/supervisor-manager-1.0.3.zip
```

Install locally on Plesk:

```sh
plesk bin extension --install build/supervisor-manager-1.0.3.zip
```

## License

Private project. Update this section before publishing publicly.

<p align="center">
  Proudly developed by <a href="https://github.com/ghostcompiler">Ghost Compiler</a>.
</p>
