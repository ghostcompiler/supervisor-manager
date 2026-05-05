<?php

class SupervisorManager_Supervisor
{
    public function status($program)
    {
        $output = $this->call('status', $program);
        return $this->parseStatus($program, $output);
    }

    public function statusMany(array $programs)
    {
        $statuses = array();
        foreach ($programs as $program) {
            try {
                $statuses[$program] = $this->status($program);
            } catch (Exception $e) {
                $statuses[$program] = array(
                    'state' => 'UNKNOWN',
                    'detail' => $e->getMessage(),
                );
            }
        }

        return $statuses;
    }

    public function start($program)
    {
        return $this->call('start', $program);
    }

    public function stop($program)
    {
        return $this->call('stop', $program);
    }

    public function restart($program)
    {
        $this->call('stop', $program);
        return $this->call('start', $program);
    }

    public function tail($program, $lines)
    {
        return $this->call('tail', $program, array('--lines=' . (int) $lines));
    }

    public function tailLog(array $program, $lines)
    {
        if (!empty($program['log_path'])) {
            return $this->call('tail-file', '', array(
                '--file=' . $program['log_path'],
                '--lines=' . (int) $lines,
            ));
        }

        return $this->tail($program['name'], $lines);
    }

    public function reload()
    {
        return $this->call('reload', '');
    }

    public function diagnostics()
    {
        $output = $this->call('diagnostics', '');
        $data = json_decode($output, true);
        if (!is_array($data)) {
            throw new pm_Exception('Unable to read Supervisor diagnostics. Raw output: ' . $this->shorten($output));
        }

        return $data;
    }

    public function install()
    {
        return $this->call('install', '');
    }

    public function writeConfig(array $program)
    {
        $payload = base64_encode(json_encode($program));
        $output = $this->call('write-config', '', array('--payload=' . $payload));
        if (!$this->configExists($program)) {
            throw new pm_Exception('Supervisor config was not created at ' . $program['config_path'] . '. Helper output: ' . $this->shorten($output));
        }

        return $output;
    }

    public function deleteConfig(array $program)
    {
        return $this->call('delete-config', '', array('--config=' . $program['config_path']));
    }

    public function configExists(array $program)
    {
        if (empty($program['config_path'])) {
            return false;
        }

        return file_exists($program['config_path']);
    }

    private function call($action, $program, array $extra = array())
    {
        $args = array_merge(array('--action=' . $action), $program !== '' ? array('--program=' . $program) : array(), $extra);
        $env = array(
            'PATH' => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
            'DEBIAN_FRONTEND' => 'noninteractive',
        );
        $result = pm_ApiCli::callSbin('supervisor-manager', $args, pm_ApiCli::RESULT_FULL, $env);

        return $this->extractOutput($result);
    }

    private function extractOutput($result)
    {
        if (is_string($result)) {
            return $result;
        }

        if (!is_array($result)) {
            return (string) $result;
        }

        $stdout = $this->arrayValue($result, array('stdout', 'out', 1), '');
        $stderr = $this->arrayValue($result, array('stderr', 'err', 2), '');
        $code = (int) $this->arrayValue($result, array('code', 'exitCode', 'exit_code', 0), 0);

        if ($code !== 0) {
            throw new pm_Exception(trim($stderr) !== '' ? trim($stderr) : 'Command failed with exit code ' . $code . '. Output: ' . $this->shorten($stdout));
        }

        if ($stdout === '' && $stderr !== '') {
            return $stderr;
        }

        return $stdout;
    }

    private function arrayValue(array $array, array $keys, $default)
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $array)) {
                return $array[$key];
            }
        }

        return $default;
    }

    private function shorten($value)
    {
        $value = trim((string) $value);
        if (strlen($value) > 500) {
            return substr($value, 0, 500) . '...';
        }

        return $value;
    }

    private function parseStatus($program, $output)
    {
        $line = trim($output);
        if ($line === '') {
            return array('state' => 'UNKNOWN', 'detail' => 'No status returned.');
        }

        $parts = preg_split('/\s+/', $line, 3);
        $state = isset($parts[1]) ? $parts[1] : 'UNKNOWN';
        if (isset($parts[0]) && $parts[0] !== $program && strpos($parts[0], $program . ':') !== 0) {
            $state = isset($parts[1]) ? $parts[1] : $state;
        }

        return array(
            'state' => strtoupper($state),
            'detail' => $line,
        );
    }
}
