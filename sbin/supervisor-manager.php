#!/usr/local/psa/admin/bin/php
<?php

$options = getopt('', array('action:', 'program::', 'lines::', 'payload::', 'config::', 'file::'));
$action = isset($options['action']) ? $options['action'] : '';
$program = isset($options['program']) ? $options['program'] : '';
$lines = isset($options['lines']) ? (int) $options['lines'] : 120;

$allowedActions = array(
    'status',
    'start',
    'stop',
    'tail',
    'tail-file',
    'clear-log',
    'reload',
    'diagnostics',
    'install',
    'setup',
    'write-config',
    'delete-config',
);
if (!in_array($action, $allowedActions, true)) {
    fwrite(STDERR, "Unsupported action.\n");
    exit(2);
}

if (in_array($action, array('status', 'start', 'stop', 'tail'), true) && !isValidProgramName($program)) {
    fwrite(STDERR, "Invalid Supervisor program name.\n");
    exit(2);
}

if ($action === 'diagnostics') {
    writeJson(diagnostics());
    exit(0);
}

if ($action === 'install') {
    installSupervisor();
    exit(0);
}

if ($action === 'setup') {
    setupSupervisor();
    exit(0);
}

if ($action === 'write-config') {
    $programData = decodePayload(isset($options['payload']) ? $options['payload'] : '');
    writeProgramConfig($programData);
    exit(0);
}

if ($action === 'delete-config') {
    deleteProgramConfig(isset($options['config']) ? $options['config'] : '');
    exit(0);
}

if ($action === 'tail-file') {
    tailFile(isset($options['file']) ? $options['file'] : '', $lines);
    exit(0);
}

if ($action === 'clear-log') {
    clearLogFile(isset($options['file']) ? $options['file'] : '');
    exit(0);
}

$supervisorctl = findSupervisorctl(true);
$base = array($supervisorctl);
$config = supervisorMainConfig();
if ($config !== null) {
    $base[] = '-c';
    $base[] = $config;
}

if ($action === 'reload') {
    runCommand(array_merge($base, array('reread')));
    runCommand(array_merge($base, array('update')));
    restartServiceIfNeeded();
    exit(0);
}

if ($action === 'tail') {
    $lines = min(500, max(20, $lines));
    runCommand(array_merge($base, array('tail', '-' . $lines, $program)));
    exit(0);
}

runCommand(array_merge($base, array($action, $program)));

function diagnostics()
{
    $os = detectOs();
    $ctl = findSupervisorctl(false);
    $service = supervisorServiceName();
    $mainConfig = supervisorMainConfig();
    $status = supervisorctlStatus($ctl, $mainConfig);
    $active = serviceIsActive($service);
    $socket = supervisorSocketPath($mainConfig);
    $socketExists = $socket !== null && file_exists($socket);
    $healthy = $ctl !== null && $status['ok'] && ($active || $socketExists);

    return array(
        'installed' => $ctl !== null,
        'healthy' => $healthy,
        'supervisorctl' => $ctl,
        'os' => $os['pretty_name'],
        'os_id' => $os['id'],
        'config_dir' => supervisorConfigDir(),
        'main_config' => $mainConfig,
        'service' => $service,
        'service_active' => $active,
        'socket' => $socket,
        'socket_exists' => $socketExists,
        'status_ok' => $status['ok'],
        'status_output' => $status['output'],
        'install_command' => installCommandForOs($os['id']),
        'supports_install' => installCommandForOs($os['id']) !== null,
    );
}

function installSupervisor()
{
    $os = detectOs();
    $command = installCommandForOs($os['id']);
    if ($command === null) {
        fwrite(STDERR, "Automatic install is not supported for this OS. Install Supervisor manually, then refresh this page.\n");
        exit(2);
    }

    runShell($command);
    setupSupervisor();
    echo "Installed using: {$command}\n";
}

function setupSupervisor()
{
    $os = detectOs();
    $service = supervisorServiceName();
    $configDir = supervisorConfigDir();
    $logDir = '/var/log/supervisor/plesk';

    if (!is_dir($configDir)) {
        mkdir($configDir, 0755, true);
    }
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    if (in_array($os['id'], array('ubuntu', 'debian'), true)) {
        setupDebianSupervisorConfig();
    }

    runShell('systemctl enable ' . escapeshellarg($service) . ' 2>/dev/null || true');
    runShell('systemctl restart ' . escapeshellarg($service) . ' 2>/dev/null || service ' . escapeshellarg($service) . ' restart');
    $ctl = findSupervisorctl(false);
    $mainConfig = supervisorMainConfig();
    $status = supervisorctlStatus($ctl, $mainConfig);
    if (!$status['ok']) {
        fwrite(STDERR, "Supervisor service was set up, but supervisorctl still cannot connect: " . trim($status['output']) . "\n");
        exit(2);
    }

    echo "Supervisor setup completed for {$os['pretty_name']} using service {$service}.\n";
}

function setupDebianSupervisorConfig()
{
    $config = supervisorMainConfig();
    if ($config === null) {
        $config = '/etc/supervisor/supervisord.conf';
    }
    $dir = dirname($config);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    if (!file_exists($config)) {
        $content = array(
            '[unix_http_server]',
            'file=/var/run/supervisor.sock',
            'chmod=0700',
            '',
            '[supervisord]',
            'logfile=/var/log/supervisor/supervisord.log',
            'pidfile=/var/run/supervisord.pid',
            'childlogdir=/var/log/supervisor',
            '',
            '[rpcinterface:supervisor]',
            'supervisor.rpcinterface_factory = supervisor.rpcinterface:make_main_rpcinterface',
            '',
            '[supervisorctl]',
            'serverurl=unix:///var/run/supervisor.sock',
            '',
            '[include]',
            'files = /etc/supervisor/conf.d/*.conf',
            '',
        );
        file_put_contents($config, implode("\n", $content), LOCK_EX);
        chmod($config, 0644);
        return;
    }

    $contents = file_get_contents($config);
    if ($contents === false) {
        return;
    }
    $changed = false;
    if (strpos($contents, '[unix_http_server]') === false) {
        $contents .= "\n[unix_http_server]\nfile=/var/run/supervisor.sock\nchmod=0700\n";
        $changed = true;
    }
    if (strpos($contents, '[supervisorctl]') === false) {
        $contents .= "\n[supervisorctl]\nserverurl=unix:///var/run/supervisor.sock\n";
        $changed = true;
    }
    if (strpos($contents, '[rpcinterface:supervisor]') === false) {
        $contents .= "\n[rpcinterface:supervisor]\nsupervisor.rpcinterface_factory = supervisor.rpcinterface:make_main_rpcinterface\n";
        $changed = true;
    }
    if (strpos($contents, '[include]') === false) {
        $contents .= "\n[include]\nfiles = /etc/supervisor/conf.d/*.conf\n";
        $changed = true;
    }
    if ($changed) {
        copy($config, $config . '.plesk-supervisor-manager.bak');
        file_put_contents($config, $contents, LOCK_EX);
        chmod($config, 0644);
    }
}

function writeProgramConfig(array $program)
{
    $name = isset($program['name']) ? trim($program['name']) : '';
    if (!isValidProgramName($name)) {
        fwrite(STDERR, "Invalid Supervisor program name.\n");
        exit(2);
    }
    $supervisorName = supervisorProgramName($program, $name);

    $command = singleLine(isset($program['command']) ? $program['command'] : '', 'Command');
    if (isDiagnosticCommand($command)) {
        fwrite(STDERR, "Command must start a long-running process. Version/help checks like php -v exit immediately and cannot be managed by Supervisor.\n");
        exit(2);
    }
    $workingDirectory = singleLine(isset($program['working_directory']) ? $program['working_directory'] : '/', 'Working directory');
    $user = normalizeProcessUser(isset($program['process_user']) ? $program['process_user'] : '');
    $configPath = configPathForProgram($program);
    $logDir = '/var/log/supervisor/plesk';
    $allowedRoots = normalizeAllowedRoots(isset($program['allowed_roots']) ? $program['allowed_roots'] : array());

    if (!is_dir($workingDirectory)) {
        fwrite(STDERR, "Project root does not exist: {$workingDirectory}\n");
        exit(2);
    }
    if (empty($allowedRoots)) {
        fwrite(STDERR, "No valid Plesk domain roots were provided for this program.\n");
        exit(2);
    }
    if (!isPathInsideAnyRoot($workingDirectory, $allowedRoots)) {
        fwrite(STDERR, "Project root is outside the selected domain area: {$workingDirectory}\n");
        exit(2);
    }
    if (preg_match('/\bartisan\b/', $command) && !file_exists($workingDirectory . '/artisan')) {
        fwrite(STDERR, "Laravel artisan was not found in project root: {$workingDirectory}. Set Project root to the folder containing artisan.\n");
        exit(2);
    }
    if (preg_match('/inertia:start-ssr|ssr/i', $command) && empty(findNodeDirectories())) {
        fwrite(STDERR, "Node.js was not found in Supervisor PATH. Install Node.js with the Plesk Node.js extension or system package, then regenerate config.\n");
        exit(2);
    }

    if (!is_dir(dirname($configPath))) {
        mkdir(dirname($configPath), 0755, true);
    }
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $logPath = logPathForProgram($program, $name);
    ensureSafeTargetFile($configPath, 'config');
    prepareLogFile($logPath);
    $autostart = !empty($program['autostart']) ? 'true' : 'false';
    $autorestart = !empty($program['autorestart']) ? 'true' : 'false';
    $phpCli = preferredPhpCliBinary($program);
    $environmentPath = buildEnvironmentPath($phpCli);
    $supervisorCommand = commandForSupervisor($command, $phpCli);

    $content = array(
        '; Generated by Plesk Supervisor Manager. Edit from Plesk when possible.',
        '[program:' . $supervisorName . ']',
        'process_name=%(program_name)s',
        'command=' . $supervisorCommand,
        'directory=' . $workingDirectory,
        'environment=PATH="' . $environmentPath . '"',
        'user=' . $user,
        'autostart=' . $autostart,
        'autorestart=' . $autorestart,
        'startsecs=3',
        'stopwaitsecs=15',
        'redirect_stderr=true',
        'stdout_logfile=' . $logPath,
        'stdout_logfile_maxbytes=20MB',
        'stdout_logfile_backups=5',
        '',
    );

    file_put_contents($configPath, implode("\n", $content), LOCK_EX);
    chmod($configPath, 0644);
    echo "Generated {$configPath}\nUsing program={$supervisorName}\nUsing command={$supervisorCommand}\nUsing directory={$workingDirectory}\nUsing PHP=" . ($phpCli !== null ? $phpCli : 'default PATH') . "\nUsing PATH={$environmentPath}\nUsing log={$logPath}\n";
}

function deleteProgramConfig($config)
{
    $config = validateConfigPath($config);
    ensureSafeTargetFile($config, 'config');
    if (file_exists($config)) {
        unlink($config);
        echo "Deleted {$config}\n";
    }
}

function decodePayload($payload)
{
    $json = base64_decode($payload, true);
    $data = json_decode($json, true);
    if (!is_array($data)) {
        fwrite(STDERR, "Invalid program payload.\n");
        exit(2);
    }

    return $data;
}

function tailFile($file, $lines)
{
    $file = validateLogPath($file);
    $lines = min(500, max(20, (int) $lines));
    if (!file_exists($file)) {
        echo "Log file does not exist yet: {$file}\nStart or restart the process, then refresh logs.\n";
        return;
    }

    runCommand(array('tail', '-' . $lines, $file));
}

function clearLogFile($file)
{
    $file = validateLogPath($file);
    $directory = dirname($file);
    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    if (!file_exists($file)) {
        touch($file);
    }
    if (is_link($file) || !is_file($file)) {
        fwrite(STDERR, "Invalid log file path.\n");
        exit(2);
    }
    $handle = fopen($file, 'c');
    if ($handle === false) {
        fwrite(STDERR, "Unable to open log file for clearing.\n");
        exit(1);
    }
    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        fwrite(STDERR, "Unable to lock log file for clearing.\n");
        exit(1);
    }
    ftruncate($handle, 0);
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    chmod($file, 0644);
    echo "Cleared {$file}\n";
}

function isPathInsideAnyRoot($path, array $roots)
{
    $realPath = realpath($path);
    if ($realPath === false) {
        return false;
    }

    foreach ($roots as $root) {
        $realRoot = realpath($root);
        if ($realRoot === false) {
            continue;
        }
        $realPath = rtrim($realPath, '/');
        $realRoot = rtrim($realRoot, '/');
        if ($realPath === $realRoot || strpos($realPath . '/', $realRoot . '/') === 0) {
            return true;
        }
    }

    return false;
}

function isPathInsideRoot($path, $root)
{
    $path = rtrim($path, '/');
    $root = rtrim($root, '/');

    return $path === $root || strpos($path . '/', $root . '/') === 0;
}

function pleskVhostBaseDirs()
{
    $dirs = array('/var/www/vhosts');
    if (is_readable('/etc/psa/psa.conf')) {
        foreach (file('/etc/psa/psa.conf') as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (preg_match('/^HTTPD_VHOSTS_D\s+(.+)$/', $line, $matches)) {
                $dirs[] = trim($matches[1]);
            }
        }
    }

    $realDirs = array();
    foreach ($dirs as $dir) {
        $realDir = realpath($dir);
        if ($realDir !== false && is_dir($realDir)) {
            $realDirs[] = rtrim($realDir, '/');
        }
    }

    return array_values(array_unique($realDirs));
}

function isValidProgramName($program)
{
    return is_string($program) && preg_match('/^[A-Za-z0-9_.:*-]+$/', $program);
}

function singleLine($value, $label)
{
    $value = trim((string) $value);
    if ($value === '' || preg_match('/[\r\n]/', $value)) {
        fwrite(STDERR, "{$label} must be a non-empty single line.\n");
        exit(2);
    }

    return $value;
}

function isDiagnosticCommand($command)
{
    return (bool) preg_match('/^\s*(?:\S+\/)?(?:php|node|npm|yarn|pnpm|python|python3|ruby|composer)\s+(?:-v|--version|-h|--help|help|version)\s*$/i', $command);
}

function commandForSupervisor($command, $preferredPhpCli = null)
{
    if ($preferredPhpCli !== null && preg_match('/^(?:\S+\/)?php(\s+.*)?$/', $command, $matches)) {
        return '/usr/bin/env php' . (isset($matches[1]) ? $matches[1] : '');
    }

    return $command;
}

function buildEnvironmentPath($preferredPhpCli = null)
{
    $paths = array();
    if ($preferredPhpCli !== null && is_executable($preferredPhpCli)) {
        $paths[] = dirname($preferredPhpCli);
    }
    foreach (findNodeDirectories() as $path) {
        $paths[] = $path;
    }
    foreach (findPhpDirectories() as $path) {
        $paths[] = $path;
    }
    foreach (array('/usr/local/sbin', '/usr/local/bin', '/usr/sbin', '/usr/bin', '/sbin', '/bin') as $path) {
        $paths[] = $path;
    }

    return implode(':', array_values(array_unique($paths)));
}

function findPhpDirectories()
{
    $paths = glob('/opt/plesk/php/*/bin') ?: array();
    usort($paths, function ($a, $b) {
        return version_compare(basename(dirname($b)), basename(dirname($a)));
    });

    return $paths;
}

function findNodeDirectories()
{
    $paths = array();
    foreach (glob('/opt/plesk/node/*/bin') ?: array() as $path) {
        if (is_executable($path . '/node')) {
            $paths[] = $path;
        }
    }
    foreach (array('/usr/local/bin', '/usr/bin', '/snap/bin') as $path) {
        if (is_executable($path . '/node')) {
            $paths[] = $path;
        }
    }

    return $paths;
}

function logPathForProgram(array $program, $name)
{
    if (!empty($program['log_path'])) {
        return validateLogPath($program['log_path']);
    }

    $safeLogName = preg_replace('/[^A-Za-z0-9_.-]+/', '-', str_replace(':', '-', $name));
    return validateLogPath('/var/log/supervisor/plesk/' . $safeLogName . '.log');
}

function preferredPhpCliBinary(array $program)
{
    if (!empty($program['domain_id'])) {
        $handlerId = domainPhpHandlerIdById((int) $program['domain_id']);
        $php = phpCliForHandler($handlerId);
        if ($php !== null) {
            return $php;
        }
    }

    $domain = '';
    if (!empty($program['domain_ascii_name'])) {
        $domain = $program['domain_ascii_name'];
    } elseif (!empty($program['domain_name'])) {
        $domain = $program['domain_name'];
    }

    if ($domain !== '') {
        $handlerId = domainPhpHandlerId($domain);
        $php = phpCliForHandler($handlerId);
        if ($php !== null) {
            return $php;
        }
    }

    return null;
}

function domainPhpHandlerIdById($domainId)
{
    if ($domainId <= 0) {
        return null;
    }

    $plesk = findPleskCli();
    if ($plesk === null) {
        return null;
    }
    $sql = 'select php_handler_id from hosting where dom_id = ' . (int) $domainId . ' limit 1';
    $result = runShellCapture(escapeshellarg($plesk) . ' db -N -B -e ' . escapeshellarg($sql));
    if ($result['code'] !== 0) {
        return null;
    }

    $handlerId = trim($result['stdout']);
    return $handlerId !== '' ? $handlerId : null;
}

function domainPhpHandlerId($domain)
{
    if (!preg_match('/^[A-Za-z0-9_.-]+$/', $domain)) {
        return null;
    }

    $sql = "select h.php_handler_id from hosting h inner join domains d on d.id = h.dom_id where d.name = '" . sqlString($domain) . "' limit 1";
    $plesk = findPleskCli();
    if ($plesk === null) {
        return null;
    }
    $result = runShellCapture(escapeshellarg($plesk) . ' db -N -B -e ' . escapeshellarg($sql));
    if ($result['code'] !== 0) {
        return null;
    }

    $handlerId = trim($result['stdout']);
    return $handlerId !== '' ? $handlerId : null;
}

function phpCliForHandler($handlerId)
{
    if (!is_string($handlerId) || $handlerId === '') {
        return null;
    }

    if (preg_match('/^plesk-php([0-9])([0-9]+)-/', $handlerId, $matches)) {
        $version = $matches[1] . '.' . $matches[2];
        $candidate = '/opt/plesk/php/' . $version . '/bin/php';
        if (is_executable($candidate)) {
            return $candidate;
        }
    }

    $plesk = findPleskCli();
    if ($plesk === null) {
        return null;
    }
    $result = runShellCapture(escapeshellarg($plesk) . ' bin php_handler --list 2>/dev/null');
    if ($result['code'] !== 0) {
        return null;
    }

    foreach (preg_split('/\r?\n/', trim($result['stdout'])) as $line) {
        $columns = preg_split('/\s+/', trim($line));
        if (empty($columns) || $columns[0] !== $handlerId) {
            continue;
        }
        foreach ($columns as $column) {
            if (preg_match('#/bin/php$#', $column) && is_executable($column)) {
                return $column;
            }
        }
    }

    return null;
}

function sqlString($value)
{
    return str_replace("'", "''", $value);
}

function findPleskCli()
{
    foreach (array('/usr/sbin/plesk', '/usr/local/psa/bin/plesk', '/opt/psa/bin/plesk') as $path) {
        if (is_executable($path)) {
            return $path;
        }
    }

    return null;
}

function supervisorProgramName(array $program, $fallbackName)
{
    $name = isset($program['supervisor_name']) ? trim($program['supervisor_name']) : '';
    if ($name === '') {
        $name = $fallbackName;
    }
    $name = preg_replace('/[^A-Za-z0-9_.-]+/', '-', str_replace(':', '-', $name));
    $name = trim($name, '-');
    if ($name === '' || !isValidProgramName($name)) {
        fwrite(STDERR, "Invalid generated Supervisor program name.\n");
        exit(2);
    }

    return $name;
}

function prepareLogFile($path)
{
    $directory = dirname($path);
    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    if (!file_exists($path)) {
        touch($path);
        chmod($path, 0644);
    }
}

function configPathForProgram(array $program)
{
    if (!empty($program['config_path'])) {
        return validateConfigPath($program['config_path']);
    }

    $domain = isset($program['domain_name']) ? $program['domain_name'] : 'domain';
    $safeName = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $domain . '-' . str_replace(':', '-', $program['name']));
    return validateConfigPath(supervisorConfigDir() . '/plesk-' . $safeName . '.conf');
}

function validateConfigPath($path)
{
    $path = singleLine($path, 'Config path');
    $directory = rtrim(dirname($path), '/');
    $file = basename($path);

    if (!in_array($directory, allowedSupervisorConfigDirs(), true) || !preg_match('/^plesk-[A-Za-z0-9_.-]+\.conf$/', $file)) {
        fwrite(STDERR, "Invalid config path.\n");
        exit(2);
    }

    return $directory . '/' . $file;
}

function validateLogPath($path)
{
    $path = singleLine($path, 'Log path');
    $directory = rtrim(dirname($path), '/');
    $file = basename($path);

    if ($directory !== '/var/log/supervisor/plesk' || !preg_match('/^[A-Za-z0-9_.-]+\.log$/', $file)) {
        fwrite(STDERR, "Invalid log file path.\n");
        exit(2);
    }

    $path = $directory . '/' . $file;
    ensureSafeTargetFile($path, 'log');
    if (file_exists($path)) {
        $realPath = realpath($path);
        $realDirectory = realpath($directory);
        if ($realPath === false || $realDirectory === false || !isPathInsideRoot($realPath, $realDirectory)) {
            fwrite(STDERR, "Invalid log file path.\n");
            exit(2);
        }
    }

    return $path;
}

function normalizeProcessUser($user)
{
    $user = singleLine($user, 'Process user');
    if ($user === 'root' || !preg_match('/^[A-Za-z_][A-Za-z0-9_.-]*[$]?$/', $user)) {
        fwrite(STDERR, "Invalid process user.\n");
        exit(2);
    }
    if (function_exists('posix_getpwnam') && posix_getpwnam($user) === false) {
        fwrite(STDERR, "Process user does not exist.\n");
        exit(2);
    }

    return $user;
}

function normalizeAllowedRoots($roots)
{
    if (!is_array($roots)) {
        return array();
    }

    $valid = array();
    foreach ($roots as $root) {
        $root = trim((string) $root);
        if ($root === '' || preg_match('/[\r\n]/', $root)) {
            continue;
        }
        $realRoot = realpath($root);
        if ($realRoot === false || !is_dir($realRoot)) {
            continue;
        }
        if (!isPathInsideAnyRoot($realRoot, pleskVhostBaseDirs())) {
            continue;
        }
        $valid[] = rtrim($realRoot, '/');
    }

    return array_values(array_unique($valid));
}

function ensureSafeTargetFile($path, $label)
{
    if (is_link($path) || !is_file($path)) {
        if (!file_exists($path) && !is_link($path)) {
            return;
        }
        fwrite(STDERR, "Invalid {$label} file path.\n");
        exit(2);
    }
}

function allowedSupervisorConfigDirs()
{
    return array('/etc/supervisor/conf.d', '/etc/supervisord.d');
}

function findSupervisorctl($required)
{
    $paths = array('/usr/bin/supervisorctl', '/usr/local/bin/supervisorctl', '/bin/supervisorctl');
    foreach ($paths as $path) {
        if (is_executable($path)) {
            return $path;
        }
    }

    if ($required) {
        fwrite(STDERR, "supervisorctl was not found. Install Supervisor first or use the Install Supervisor button.\n");
        exit(127);
    }

    return null;
}

function supervisorConfigDir()
{
    if (is_dir('/etc/supervisor/conf.d')) {
        return '/etc/supervisor/conf.d';
    }
    if (is_dir('/etc/supervisord.d')) {
        return '/etc/supervisord.d';
    }

    return '/etc/supervisor/conf.d';
}

function supervisorMainConfig()
{
    $paths = array('/etc/supervisor/supervisord.conf', '/etc/supervisord.conf');
    foreach ($paths as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }

    return null;
}

function supervisorSocketPath($config)
{
    if ($config !== null && is_readable($config)) {
        $inUnixServer = false;
        foreach (file($config) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === ';' || $line[0] === '#') {
                continue;
            }
            if (preg_match('/^\[(.+)\]$/', $line, $matches)) {
                $inUnixServer = $matches[1] === 'unix_http_server';
                continue;
            }
            if ($inUnixServer && preg_match('/^file\s*=\s*(.+)$/', $line, $matches)) {
                return trim($matches[1], "\"'");
            }
        }
    }

    return '/var/run/supervisor.sock';
}

function supervisorServiceName()
{
    if (file_exists('/etc/systemd/system/supervisord.service') || file_exists('/usr/lib/systemd/system/supervisord.service')) {
        return 'supervisord';
    }

    return 'supervisor';
}

function serviceIsActive($service)
{
    $result = runShellCapture('systemctl is-active ' . escapeshellarg($service));
    return $result['code'] === 0 && trim($result['stdout']) === 'active';
}

function supervisorctlStatus($ctl, $mainConfig)
{
    if ($ctl === null) {
        return array('ok' => false, 'output' => 'supervisorctl was not found.');
    }

    $parts = array($ctl);
    if ($mainConfig !== null) {
        $parts[] = '-c';
        $parts[] = $mainConfig;
    }
    $parts[] = 'status';
    $result = runCommandCapture($parts);
    return array(
        'ok' => $result['code'] === 0,
        'output' => trim($result['stdout'] . "\n" . $result['stderr']),
    );
}

function restartServiceIfNeeded()
{
    $service = supervisorServiceName();
    runShell('systemctl reload ' . escapeshellarg($service) . ' 2>/dev/null || true');
}

function detectOs()
{
    $data = array('id' => 'unknown', 'pretty_name' => php_uname('s'));
    if (!file_exists('/etc/os-release')) {
        return $data;
    }

    foreach (file('/etc/os-release') as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '=') === false) {
            continue;
        }
        list($key, $value) = explode('=', $line, 2);
        $value = trim($value, "\"'");
        if ($key === 'ID') {
            $data['id'] = strtolower($value);
        } elseif ($key === 'PRETTY_NAME') {
            $data['pretty_name'] = $value;
        }
    }

    return $data;
}

function installCommandForOs($osId)
{
    if (in_array($osId, array('ubuntu', 'debian'), true)) {
        return 'DEBIAN_FRONTEND=noninteractive apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -y supervisor';
    }
    if (in_array($osId, array('centos', 'rhel', 'almalinux', 'rocky', 'fedora'), true)) {
        return '(command -v dnf >/dev/null 2>&1 && dnf install -y supervisor) || yum install -y supervisor';
    }

    return null;
}

function runShell($command)
{
    $result = runShellCapture($command);
    if ($result['stdout'] !== '') {
        fwrite(STDOUT, $result['stdout']);
    }
    if ($result['stderr'] !== '') {
        fwrite(STDERR, $result['stderr']);
    }
    if ($result['code'] !== 0) {
        exit($result['code']);
    }
}

function runCommandCapture(array $parts)
{
    return runShellCapture(implode(' ', array_map('escapeshellarg', $parts)));
}

function runShellCapture($command)
{
    $descriptors = array(
        0 => array('pipe', 'r'),
        1 => array('pipe', 'w'),
        2 => array('pipe', 'w'),
    );

    $process = proc_open($command, $descriptors, $pipes);
    if (!is_resource($process)) {
        return array('code' => 1, 'stdout' => '', 'stderr' => 'Unable to execute command.');
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $code = proc_close($process);

    return array('code' => $code, 'stdout' => $stdout, 'stderr' => $stderr);
}

function runCommand(array $parts)
{
    runShell(implode(' ', array_map('escapeshellarg', $parts)));
}

function writeJson(array $data)
{
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
}
