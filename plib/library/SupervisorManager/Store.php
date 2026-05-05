<?php

require_once pm_Context::getPlibDir() . '/library/SupervisorManager/Permissions.php';

class SupervisorManager_Store
{
    const FILE_NAME = 'data/programs.json';

    public function all()
    {
        $items = array_map(array($this, 'enrichLegacyItem'), $this->compactDuplicates($this->read()));
        usort($items, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        return $items;
    }

    public function visibleForCurrentUser($domainId = null)
    {
        $client = SupervisorManager_Permissions::currentClient();
        $items = $this->all();

        if ($client->isAdmin()) {
            return $this->filterByDomain($items, $domainId);
        }

        $visible = array();
        foreach ($items as $item) {
            if (!SupervisorManager_Permissions::can(SupervisorManager_Permissions::ACCESS, (int) $item['domain_id'])) {
                continue;
            }
            $visible[] = $item;
        }

        return $this->filterByDomain($visible, $domainId);
    }

    public function getVisible($id)
    {
        foreach ($this->visibleForCurrentUser() as $item) {
            if ($item['id'] === $id) {
                return $item;
            }
        }

        throw new pm_Exception('Program not found or access denied.');
    }

    public function get($id)
    {
        foreach ($this->all() as $item) {
            if ($item['id'] === $id) {
                return $item;
            }
        }

        throw new pm_Exception('Program not found.');
    }

    public function save($data, $id = null)
    {
        $items = $this->all();
        $now = date('c');
        $saved = null;
        $data = $this->applyExistingIdForSameProgram($items, $data, $id);
        $id = $data['id'];

        if ($id === null) {
            $data['id'] = $this->newId();
            $data['created_at'] = $now;
            $saved = $this->normalize($data);
            $items[] = $saved;
        } else {
            $updated = false;
            foreach ($items as $index => $item) {
                if ($item['id'] === $id) {
                    $data['id'] = $id;
                    $data['created_at'] = isset($item['created_at']) ? $item['created_at'] : $now;
                    $saved = $this->normalize($data);
                    $items[$index] = $saved;
                    $updated = true;
                    break;
                }
            }
            if (!$updated) {
                throw new pm_Exception('Program not found.');
            }
        }

        $this->write($this->compactDuplicates($items));

        return $saved;
    }

    public function delete($id)
    {
        $items = array();
        foreach ($this->all() as $item) {
            if ($item['id'] !== $id) {
                $items[] = $item;
            }
        }
        $this->write($items);
    }

    public function countForDomain($domainId, $excludeId = null)
    {
        $count = 0;
        foreach ($this->all() as $item) {
            if ($excludeId !== null && $item['id'] === $excludeId) {
                continue;
            }
            if ((int) $item['domain_id'] === (int) $domainId) {
                $count++;
            }
        }

        return $count;
    }

    public function findIdByDomainAndName($domainId, $name)
    {
        $name = trim($name);
        foreach ($this->all() as $item) {
            if ((int) $item['domain_id'] === (int) $domainId && $item['name'] === $name) {
                return $item['id'];
            }
        }

        return null;
    }

    public function domains()
    {
        $domains = array();
        foreach (pm_Domain::getAllDomains(false) as $domain) {
            if (!$domain->hasHosting()) {
                continue;
            }
            $domains[$domain->getId()] = $domain->getDisplayName();
        }
        natcasesort($domains);

        return $domains;
    }

    private function normalize($data)
    {
        $domain = new pm_Domain((int) $data['domain_id']);

        $name = trim($data['name']);
        if (!preg_match('/^[A-Za-z0-9_.:*-]+$/', $name)) {
            throw new pm_Exception('Supervisor program name contains unsupported characters.');
        }

        $command = trim($data['command']);
        if ($command === '') {
            throw new pm_Exception('Command is required.');
        }
        if (preg_match('/[\r\n]/', $command)) {
            throw new pm_Exception('Command must be a single line.');
        }

        $workingDirectory = trim($data['working_directory']);
        if ($workingDirectory === '') {
            $workingDirectory = $this->defaultProjectRoot($domain, $command);
        }
        if (preg_match('/[\r\n]/', $workingDirectory)) {
            throw new pm_Exception('Working directory must be a single line.');
        }
        $allowedRoots = $this->allowedProjectRoots($domain, $command);
        $workingDirectory = $this->normalizeProjectRoot($domain, $command, $workingDirectory);

        $processUser = $domain->getSysUserLogin();
        $safeName = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $domain->getName() . '-' . str_replace(':', '-', $name));
        $configDir = is_dir('/etc/supervisord.d') && !is_dir('/etc/supervisor/conf.d') ? '/etc/supervisord.d' : '/etc/supervisor/conf.d';
        $configPath = $configDir . '/plesk-' . $safeName . '.conf';
        $logPath = '/var/log/supervisor/plesk/' . $safeName . '.log';

        return array(
            'id' => $data['id'],
            'name' => $name,
            'display_name' => trim($data['display_name']) !== '' ? trim($data['display_name']) : $name,
            'domain_id' => (int) $domain->getId(),
            'domain_name' => $domain->getDisplayName(),
            'command' => $command,
            'working_directory' => $workingDirectory,
            'allowed_roots' => $allowedRoots,
            'process_user' => $processUser,
            'autostart' => !empty($data['autostart']),
            'autorestart' => !empty($data['autorestart']),
            'config_path' => $configPath,
            'log_path' => $logPath,
            'description' => trim($data['description']),
            'enabled' => !empty($data['enabled']),
            'created_at' => $data['created_at'],
            'updated_at' => date('c'),
        );
    }

    private function enrichLegacyItem(array $item)
    {
        if (empty($item['log_path'])) {
            if (!empty($item['config_path'])) {
                $base = basename($item['config_path'], '.conf');
                $item['log_path'] = '/var/log/supervisor/plesk/' . preg_replace('/^plesk-/', '', $base) . '.log';
            } else {
                $safeName = preg_replace('/[^A-Za-z0-9_.-]+/', '-', str_replace(':', '-', $item['name']));
                $item['log_path'] = '/var/log/supervisor/plesk/' . $safeName . '.log';
            }
        }

        return $item;
    }

    private function applyExistingIdForSameProgram(array $items, array $data, $id)
    {
        if ($id !== null && $id !== '') {
            $data['id'] = $id;
            return $data;
        }

        $name = trim($data['name']);
        $domainId = (int) $data['domain_id'];
        for ($i = count($items) - 1; $i >= 0; $i--) {
            $item = $items[$i];
            if ((int) $item['domain_id'] === $domainId && $item['name'] === $name) {
                $data['id'] = $item['id'];
                return $data;
            }
        }

        $data['id'] = null;
        return $data;
    }

    private function defaultProjectRoot($domain, $command)
    {
        $documentRoot = $domain->getDocumentRoot();
        if (preg_match('/\bartisan\b/', $command)) {
            $candidates = array(
                dirname($documentRoot),
                $documentRoot,
                dirname(dirname($documentRoot)),
            );
            foreach ($candidates as $candidate) {
                if ($candidate && file_exists($candidate . '/artisan')) {
                    return $candidate;
                }
            }
        }

        return $documentRoot;
    }

    private function normalizeProjectRoot($domain, $command, $workingDirectory)
    {
        $realWorkingDirectory = realpath($workingDirectory);
        if ($realWorkingDirectory === false || !is_dir($realWorkingDirectory)) {
            throw new pm_Exception('Project root does not exist: ' . $workingDirectory);
        }

        $allowedRoots = $this->allowedProjectRoots($domain, $command);
        foreach ($allowedRoots as $allowedRoot) {
            if ($this->isPathInside($realWorkingDirectory, $allowedRoot)) {
                return $realWorkingDirectory;
            }
        }

        throw new pm_Exception('Project root is outside the selected domain area. Use a path inside: ' . implode(', ', $allowedRoots));
    }

    private function allowedProjectRoots($domain, $command)
    {
        $documentRoot = realpath($domain->getDocumentRoot());
        $roots = array();

        if ($documentRoot !== false) {
            $roots[] = $documentRoot;

            $parent = dirname($documentRoot);
            if (preg_match('/\bartisan\b/', $command) && file_exists($parent . '/artisan')) {
                $roots[] = $parent;
            }

            $domainName = $domain->getName();
            if ($domainName && strpos($documentRoot, '/var/www/vhosts/') === 0) {
                $parts = explode('/', trim($documentRoot, '/'));
                if (count($parts) >= 4) {
                    $roots[] = '/' . $parts[0] . '/' . $parts[1] . '/' . $parts[2] . '/' . $domainName;
                }
            }
        }

        $roots = array_filter(array_map('realpath', array_unique($roots)));

        return array_values(array_unique($roots));
    }

    private function isPathInside($path, $root)
    {
        $path = rtrim($path, '/');
        $root = rtrim($root, '/');

        return $path === $root || strpos($path . '/', $root . '/') === 0;
    }

    private function compactDuplicates(array $items)
    {
        $result = array();
        $seen = array();

        for ($i = count($items) - 1; $i >= 0; $i--) {
            $item = $items[$i];
            $key = (int) $item['domain_id'] . ':' . $item['name'];
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $result[] = $item;
        }

        return array_reverse($result);
    }

    private function filterByDomain($items, $domainId)
    {
        if ($domainId === null || $domainId === '') {
            return $items;
        }

        $filtered = array();
        foreach ($items as $item) {
            if ($this->belongsToDomainContext($item, $domainId)) {
                $filtered[] = $item;
            }
        }

        return $filtered;
    }

    public function belongsToDomainContext(array $item, $domainId)
    {
        if ($domainId === null || $domainId === '') {
            return true;
        }

        return (int) $item['domain_id'] === (int) $domainId;
    }

    private function read()
    {
        $file = $this->path();
        if (!file_exists($file)) {
            return array();
        }

        $contents = file_get_contents($file);
        if ($contents === false || trim($contents) === '') {
            return array();
        }

        $data = json_decode($contents, true);
        if (!is_array($data)) {
            throw new pm_Exception('Supervisor Manager data file is invalid JSON.');
        }

        return $data;
    }

    private function write(array $items)
    {
        $file = $this->path();
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $tmp = $file . '.tmp';
        file_put_contents($tmp, json_encode(array_values($items), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
        chmod($tmp, 0640);
        rename($tmp, $file);
    }

    private function path()
    {
        return pm_Context::getVarDir() . '/' . self::FILE_NAME;
    }

    private function newId()
    {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes(8));
        }

        return md5(uniqid('', true));
    }
}
