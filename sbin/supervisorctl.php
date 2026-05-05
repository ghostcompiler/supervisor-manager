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
    'reload',
    'diagnostics',
    'install',
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

    return array(
        'installed' => $ctl !== null,
        'supervisorctl' => $ctl,
        'os' => $os['pretty_name'],
        'os_id' => $os['id'],
        'config_dir' => supervisorConfigDir(),
        'main_config' => supervisorMainConfig(),
        'service' => supervisorServiceName(),
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
    $service = supervisorServiceName();
    runShell('systemctl enable --now ' . escapeshellarg($service) . ' || service ' . escapeshellarg($service) . ' start || true');
    echo "Installed using: {$command}\n";
}

function writeProgramConfig(array $program)
{
    $name = isset($program['name']) ? trim($program['name']) : '';
    if (!isValidProgramName($name)) {
        fwrite(STDERR, "Invalid Supervisor program name.\n");
        exit(2);
    }

    $command = normalizeProgramCommand(singleLine(isset($program['command']) ? $program['command'] : '', 'Command'));
    $workingDirectory = singleLine(isset($program['working_directory']) ? $program['working_directory'] : '/', 'Working directory');
    $user = singleLine(isset($program['process_user']) ? $program['process_user'] : 'root', 'Process user');
    $configPath = configPathForProgram($program);
    $logDir = '/var/log/supervisor/plesk';

    if (!is_dir($workingDirectory)) {
        fwrite(STDERR, "Project root does not exist: {$workingDirectory}\n");
        exit(2);
    }
    if (!empty($program['allowed_roots']) && !isPathInsideAnyRoot($workingDirectory, $program['allowed_roots'])) {
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
    $autostart = !empty($program['autostart']) ? 'true' : 'false';
    $autorestart = !empty($program['autorestart']) ? 'true' : 'false';
    $environmentPath = buildEnvironmentPath();

    $content = array(
        '; Generated by Plesk Supervisor Manager. Edit from Plesk when possible.',
        '[program:' . $name . ']',
        'command=' . $command,
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
    echo "Generated {$configPath}\nUsing directory={$workingDirectory}\nUsing PATH={$environmentPath}\nUsing log={$logPath}\n";
}

function deleteProgramConfig($config)
{
    $config = trim($config);
    if ($config === '' || strpos($config, '/etc/') !== 0 || strpos($config, '..') !== false) {
        fwrite(STDERR, "Invalid config path.\n");
        exit(2);
    }
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
    $file = trim($file);
    $lines = min(500, max(20, (int) $lines));
    if ($file === '' || strpos($file, '/var/log/supervisor/plesk/') !== 0 || strpos($file, '..') !== false) {
        fwrite(STDERR, "Invalid log file path.\n");
        exit(2);
    }
    if (!file_exists($file)) {
        echo "Log file does not exist yet: {$file}\nStart or restart the process, then refresh logs.\n";
        return;
    }

    runCommand(array('tail', '-' . $lines, $file));
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

function normalizeProgramCommand($command)
{
    if (preg_match('/^php\s+(.+)$/', $command, $matches)) {
        $php = findBestPhpBinary();
        if ($php !== null) {
            return $php . ' ' . $matches[1];
        }
    }

    return $command;
}

function buildEnvironmentPath()
{
    $paths = array();
    foreach (findNodeDirectories() as $path) {
        $paths[] = $path;
    }
    foreach (glob('/opt/plesk/php/*/bin') ?: array() as $path) {
        $paths[] = $path;
    }
    foreach (array('/usr/local/sbin', '/usr/local/bin', '/usr/sbin', '/usr/bin', '/sbin', '/bin') as $path) {
        $paths[] = $path;
    }

    return implode(':', array_values(array_unique($paths)));
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
        return $program['log_path'];
    }

    $safeLogName = preg_replace('/[^A-Za-z0-9_.-]+/', '-', str_replace(':', '-', $name));
    return '/var/log/supervisor/plesk/' . $safeLogName . '.log';
}

function findBestPhpBinary()
{
    $candidates = glob('/opt/plesk/php/*/bin/php');
    if (is_array($candidates) && !empty($candidates)) {
        usort($candidates, function ($a, $b) {
            return version_compare(basename(dirname(dirname($b))), basename(dirname(dirname($a))));
        });
        foreach ($candidates as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }
    }

    foreach (array('/usr/bin/php', '/usr/local/bin/php') as $candidate) {
        if (is_executable($candidate)) {
            return $candidate;
        }
    }

    return null;
}

function configPathForProgram(array $program)
{
    if (!empty($program['config_path'])) {
        return $program['config_path'];
    }

    $domain = isset($program['domain_name']) ? $program['domain_name'] : 'domain';
    $safeName = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $domain . '-' . str_replace(':', '-', $program['name']));
    return supervisorConfigDir() . '/plesk-' . $safeName . '.conf';
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

function supervisorServiceName()
{
    if (file_exists('/etc/systemd/system/supervisord.service') || file_exists('/usr/lib/systemd/system/supervisord.service')) {
        return 'supervisord';
    }

    return 'supervisor';
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
    $descriptors = array(
        0 => array('pipe', 'r'),
        1 => array('pipe', 'w'),
        2 => array('pipe', 'w'),
    );

    $process = proc_open($command, $descriptors, $pipes);
    if (!is_resource($process)) {
        fwrite(STDERR, "Unable to execute command.\n");
        exit(1);
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $code = proc_close($process);
    if ($stdout !== '') {
        fwrite(STDOUT, $stdout);
    }
    if ($stderr !== '') {
        fwrite(STDERR, $stderr);
    }
    if ($code !== 0) {
        exit($code);
    }
}

function runCommand(array $parts)
{
    runShell(implode(' ', array_map('escapeshellarg', $parts)));
}

function writeJson(array $data)
{
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
}
