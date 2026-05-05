<?php

require_once pm_Context::getPlibDir() . '/library/SupervisorManager/Store.php';
require_once pm_Context::getPlibDir() . '/library/SupervisorManager/Supervisor.php';
require_once pm_Context::getPlibDir() . '/library/SupervisorManager/Permissions.php';

class IndexController extends pm_Controller_Action
{
    protected $_accessLevel = array('admin', 'reseller', 'client');

    const CREATOR_NAME = 'Ghost Compiler';
    const CREATOR_EMAIL = 'hello@ghostcompiler.in';
    const CREATOR_PROFILE = 'https://github.com/ghostcompiler';
    const GITHUB_URL = 'https://github.com/ghostcompiler/supervisor-manager';
    const LOGO_URL = 'https://assets.ghostcompiler.in/logo.png';

    private $store;
    private $supervisor;

    public function init()
    {
        parent::init();
        $this->store = new SupervisorManager_Store();
        $this->supervisor = new SupervisorManager_Supervisor();
        $this->view->headLink()->appendStylesheet(pm_Context::getBaseUrl() . 'assets/app.css');
        $this->view->extensionInfo = $this->extensionInfo();
    }

    public function indexAction()
    {
        $domainId = $this->currentDomainId();
        $accessError = $this->indexAccessError($domainId);
        $programs = array();
        if ($accessError === null) {
            $programs = SupervisorManager_Permissions::filterPrograms(
                $this->store->visibleForCurrentUser($domainId),
                SupervisorManager_Permissions::ACCESS
            );
        }
        $names = array();
        foreach ($programs as $program) {
            if (!empty($program['enabled'])) {
                $names[] = $program['name'];
            }
        }

        $this->view->programs = $programs;
        $this->view->statuses = $this->supervisor->statusMany($names);
        $this->view->configExists = $this->configExistsMap($programs);
        $this->view->diagnostics = $this->safeDiagnostics();
        $flash = $this->pullFlash();
        $this->view->notice = isset($flash['notice']) ? $flash['notice'] : null;
        $this->view->error = $accessError ?: (isset($flash['error']) ? $flash['error'] : null);
        $this->view->isAdmin = $this->currentClientIsAdmin();
        $this->view->canCreate = $this->canCreate($domainId);
        $this->view->canReread = $this->currentClientIsAdmin();
        $this->view->canInstall = $this->currentClientIsAdmin();
        $this->view->programPermissions = $this->programPermissions($programs);
        $this->view->domainId = $domainId;
        $this->view->domainContext = $domainId !== null && $domainId !== '';
        $this->view->domainName = $this->domainName($domainId);
        $this->view->domainQuery = $this->domainQuery();
        $this->view->showInfo = $this->_request->getParam('info') === '1';
    }

    public function addAction()
    {
        $domainId = $this->currentDomainId();
        $formData = $this->formData();
        if ($domainId !== null && $domainId !== '') {
            SupervisorManager_Permissions::assertDomain(SupervisorManager_Permissions::MANAGE, $domainId);
            SupervisorManager_Permissions::assertProgramLimit($domainId, $this->store->countForDomain($domainId));
            $formData['domain_id'] = (int) $domainId;
            $domains = $this->store->domains();
        } else {
            $domains = $this->creatableDomains();
            if (empty($domains)) {
                throw new pm_Exception('Supervisor program management is not enabled for any domain, or the program limit has been reached.');
            }
        }
        $this->view->formData = $formData;
        $this->view->domains = $domains;
        $this->view->domainContext = $domainId !== null && $domainId !== '';
        $this->view->domainId = $domainId;
        $this->view->domainName = $this->domainName($domainId);
        $this->view->domainQuery = $this->domainQuery();
    }

    public function editAction()
    {
        $domainId = $this->currentDomainId();
        $program = $this->getProgramForAction($this->_request->getParam('id'), SupervisorManager_Permissions::MANAGE);
        $this->view->formData = $this->formData($program);
        $this->view->domains = SupervisorManager_Permissions::filterDomains(
            $this->store->domains(),
            SupervisorManager_Permissions::MANAGE
        );
        $this->view->domainContext = $domainId !== null && $domainId !== '';
        $this->view->domainId = $domainId;
        $this->view->domainName = $this->domainName($domainId);
        $this->view->domainQuery = $this->domainQuery();
    }

    public function saveAction()
    {
        $this->requirePost();

        $data = array(
            'name' => $this->_request->getPost('name'),
            'display_name' => $this->_request->getPost('display_name'),
            'domain_id' => $this->_request->getPost('domain_id'),
            'command' => $this->_request->getPost('command'),
            'working_directory' => $this->_request->getPost('working_directory'),
            'autostart' => $this->_request->getPost('autostart') ? 1 : 0,
            'autorestart' => $this->_request->getPost('autorestart') ? 1 : 0,
            'description' => $this->_request->getPost('description'),
            'enabled' => $this->_request->getPost('enabled') ? 1 : 0,
        );

        try {
            $id = $this->_request->getPost('id');
            $existingProgram = null;
            if ($id !== null && $id !== '') {
                $existingProgram = $this->getProgramForAction($id, SupervisorManager_Permissions::MANAGE);
            }
            SupervisorManager_Permissions::assertDomain(SupervisorManager_Permissions::MANAGE, $data['domain_id']);
            $this->assertProgramCapacity($data, $id ?: null, $existingProgram);
            $program = $this->store->save($data, $id ?: null);
            $this->supervisor->writeConfig($program);
            try {
                $this->supervisor->reload();
                return $this->redirectWithNotice('Program saved and Supervisor config generated.');
            } catch (Exception $reloadException) {
                return $this->redirectWithNotice('Program saved and config file generated, but Supervisor reload failed: ' . $reloadException->getMessage());
            }
        } catch (Exception $e) {
            $this->_status->addMessage('error', $e->getMessage());
            $this->view->error = $e->getMessage();
            $this->view->formData = $this->formData(array_merge($data, array('id' => $this->_request->getPost('id'))));
            $this->view->domains = SupervisorManager_Permissions::filterDomains(
                $this->store->domains(),
                SupervisorManager_Permissions::MANAGE
            );
            $domainId = $this->currentDomainId();
            $this->view->domainContext = $domainId !== null && $domainId !== '';
            $this->view->domainId = $domainId;
            $this->view->domainName = $this->domainName($domainId);
            $this->view->domainQuery = $this->domainQuery();
            $this->render('add');
        }
    }

    public function deleteAction()
    {
        $this->requirePost();
        $program = $this->getProgramForAction($this->_request->getPost('id'), SupervisorManager_Permissions::MANAGE);
        try {
            $this->supervisor->deleteConfig($program);
            $this->supervisor->reload();
        } catch (Exception $e) {
            $this->_status->addMessage('warning', 'Program removed from Plesk, but Supervisor config cleanup failed: ' . $e->getMessage());
        }
        $this->store->delete($program['id']);
        return $this->redirectWithNotice('Program removed.');
    }

    public function generateAction()
    {
        $this->requirePost();

        $program = $this->getProgramForAction($this->_request->getPost('id'), SupervisorManager_Permissions::MANAGE);
        try {
            $this->supervisor->writeConfig($program);
            try {
                $this->supervisor->reload();
                return $this->redirectWithNotice('Config generated and Supervisor reloaded.');
            } catch (Exception $reloadException) {
                return $this->redirectWithNotice('Config generated, but Supervisor reload failed: ' . $reloadException->getMessage());
            }
        } catch (Exception $e) {
            return $this->redirectWithError($e->getMessage());
        }
    }

    public function actionAction()
    {
        $this->requirePost();

        $id = $this->_request->getPost('id');
        $action = $this->_request->getPost('program_action');
        $program = $this->getProgramForAction($id, SupervisorManager_Permissions::CONTROL);

        if (empty($program['enabled'])) {
            throw new pm_Exception('This program is disabled in Supervisor Manager.');
        }

        try {
            if ($action === 'start') {
                $this->supervisor->start($program['name']);
            } elseif ($action === 'stop') {
                $this->supervisor->stop($program['name']);
            } elseif ($action === 'restart') {
                $this->supervisor->restart($program['name']);
            } else {
                throw new pm_Exception('Unsupported action.');
            }
            return $this->redirectWithNotice(ucfirst($action) . ' requested for ' . $program['display_name'] . '.');
        } catch (Exception $e) {
            return $this->redirectWithError($e->getMessage());
        }
    }

    public function logsAction()
    {
        $domainId = $this->currentDomainId();
        $program = $this->getProgramForAction($this->_request->getParam('id'), SupervisorManager_Permissions::LOGS);
        $lines = min(500, max(20, (int) $this->_request->getParam('lines', 120)));

        $this->view->program = $program;
        $this->view->lines = $lines;
        $this->view->domainContext = $domainId !== null && $domainId !== '';
        $this->view->domainId = $domainId;
        $this->view->domainQuery = $this->domainQuery();

        try {
            $this->view->log = $this->supervisor->tailLog($program, $lines);
        } catch (Exception $e) {
            $this->view->log = $e->getMessage();
        }
    }

    public function logDataAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        $domainId = $this->currentDomainId();
        $program = $this->getProgramForAction($this->_request->getParam('id'), SupervisorManager_Permissions::LOGS);
        $lines = min(500, max(20, (int) $this->_request->getParam('lines', 120)));

        try {
            $log = $this->supervisor->tailLog($program, $lines);
            $payload = array(
                'ok' => true,
                'log' => $log,
                'updated_at' => date('H:i:s'),
            );
        } catch (Exception $e) {
            $payload = array(
                'ok' => false,
                'log' => $e->getMessage(),
                'updated_at' => date('H:i:s'),
            );
        }

        $this->getResponse()
            ->setHeader('Content-Type', 'application/json')
            ->setBody(json_encode($payload));
    }

    public function domainAction()
    {
        $domainId = $this->currentDomainId();
        if ($domainId === null || $domainId === '') {
            return $this->redirectWithError('Unable to detect the selected domain. Open Supervisor from the domain card again.');
        }

        $this->_helper->viewRenderer->setNoRender(true);
        $this->_helper->layout()->disableLayout();
        $this->getResponse()->setRedirect(pm_Context::getBaseUrl() . 'index.php/index/index?site_id=' . urlencode($domainId));
    }

    public function reloadAction()
    {
        $this->requireAdmin();
        $this->requirePost();

        try {
            $this->supervisor->reload();
            return $this->redirectWithNotice('Supervisor reread and update completed.');
        } catch (Exception $e) {
            return $this->redirectWithError($e->getMessage());
        }
    }

    public function installAction()
    {
        $this->requireAdmin();
        $this->requirePost();

        try {
            $output = $this->supervisor->install();
            return $this->redirectWithNotice('Supervisor install command completed. ' . trim($output));
        } catch (Exception $e) {
            return $this->redirectWithError($e->getMessage());
        }
    }

    private function formData(array $program = array())
    {
        return array(
            'id' => isset($program['id']) ? $program['id'] : '',
            'name' => isset($program['name']) ? $program['name'] : '',
            'display_name' => isset($program['display_name']) ? $program['display_name'] : '',
            'domain_id' => isset($program['domain_id']) ? $program['domain_id'] : '',
            'command' => isset($program['command']) ? $program['command'] : '',
            'working_directory' => isset($program['working_directory']) ? $program['working_directory'] : '',
            'autostart' => array_key_exists('autostart', $program) ? (int) $program['autostart'] : 1,
            'autorestart' => array_key_exists('autorestart', $program) ? (int) $program['autorestart'] : 1,
            'description' => isset($program['description']) ? $program['description'] : '',
            'enabled' => array_key_exists('enabled', $program) ? (int) $program['enabled'] : 1,
        );
    }

    private function extensionInfo()
    {
        $version = $this->moduleVersion();

        return array(
            'name' => 'Supervisor Manager',
            'version' => $version,
            'creator' => self::CREATOR_NAME,
            'email' => self::CREATOR_EMAIL,
            'profile_url' => self::CREATOR_PROFILE,
            'github_url' => self::GITHUB_URL,
            'logo_url' => self::LOGO_URL,
            'install_url' => self::GITHUB_URL . '/releases/download/latest/supervisor-manager.zip',
        );
    }

    private function moduleVersion()
    {
        $metaFile = dirname(pm_Context::getPlibDir()) . '/meta.xml';
        if (is_readable($metaFile)) {
            $meta = @simplexml_load_file($metaFile);
            if ($meta !== false && isset($meta->version)) {
                return (string) $meta->version;
            }
        }

        return '1.0.0';
    }

    private function requireAdmin()
    {
        if (!$this->currentClientIsAdmin()) {
            throw new pm_Exception('Permission denied.');
        }
    }

    private function currentClientIsAdmin()
    {
        return SupervisorManager_Permissions::currentClient()->isAdmin();
    }

    private function requireIndexAccess($domainId)
    {
        $error = $this->indexAccessError($domainId);
        if ($error !== null) {
            throw new pm_Exception($error);
        }
    }

    private function indexAccessError($domainId)
    {
        if ($this->currentClientIsAdmin()) {
            return null;
        }

        if ($domainId !== null && $domainId !== '') {
            return SupervisorManager_Permissions::can(SupervisorManager_Permissions::ACCESS, $domainId)
                ? null
                : 'Supervisor Manager is not enabled for this domain. Enable the Supervisor Manager permissions and sync the subscription.';
        }

        return SupervisorManager_Permissions::canAny(SupervisorManager_Permissions::ACCESS)
            ? null
            : 'Supervisor Manager is not enabled for this account. Enable the Supervisor Manager permissions and sync the subscription.';
    }

    private function canCreate($domainId)
    {
        if ($this->currentClientIsAdmin()) {
            return true;
        }

        if ($domainId !== null && $domainId !== '') {
            return SupervisorManager_Permissions::can(SupervisorManager_Permissions::MANAGE, $domainId)
                && SupervisorManager_Permissions::hasCapacity($domainId, $this->store->countForDomain($domainId));
        }

        foreach (SupervisorManager_Permissions::filterDomains($this->store->domains(), SupervisorManager_Permissions::MANAGE) as $id => $name) {
            if (SupervisorManager_Permissions::hasCapacity($id, $this->store->countForDomain($id))) {
                return true;
            }
        }

        return false;
    }

    private function creatableDomains()
    {
        $domains = SupervisorManager_Permissions::filterDomains(
            $this->store->domains(),
            SupervisorManager_Permissions::MANAGE
        );

        if ($this->currentClientIsAdmin()) {
            return $domains;
        }

        $creatable = array();
        foreach ($domains as $id => $name) {
            if (SupervisorManager_Permissions::hasCapacity($id, $this->store->countForDomain($id))) {
                $creatable[$id] = $name;
            }
        }

        return $creatable;
    }

    private function programPermissions(array $programs)
    {
        $permissions = array();
        foreach ($programs as $program) {
            $permissions[$program['id']] = array(
                'control' => SupervisorManager_Permissions::can(SupervisorManager_Permissions::CONTROL, $program['domain_id']),
                'logs' => SupervisorManager_Permissions::can(SupervisorManager_Permissions::LOGS, $program['domain_id']),
                'manage' => SupervisorManager_Permissions::can(SupervisorManager_Permissions::MANAGE, $program['domain_id']),
            );
        }

        return $permissions;
    }

    private function getProgramForAction($id, $permission)
    {
        $program = $this->currentClientIsAdmin() ? $this->store->get($id) : $this->store->getVisible($id);
        $this->requireProgramInCurrentDomain($program);
        SupervisorManager_Permissions::assertProgram($program, $permission);

        return $program;
    }

    private function assertProgramCapacity(array $data, $id, $existingProgram)
    {
        $domainId = (int) $data['domain_id'];

        if ($existingProgram !== null && (int) $existingProgram['domain_id'] === $domainId) {
            return;
        }

        if ($id === null || $id === '') {
            $duplicateId = $this->store->findIdByDomainAndName($domainId, $data['name']);
            if ($duplicateId !== null) {
                return;
            }
        }

        SupervisorManager_Permissions::assertProgramLimit(
            $domainId,
            $this->store->countForDomain($domainId, $id ?: null)
        );
    }

    private function requirePost()
    {
        if (!$this->_request->isPost()) {
            throw new pm_Exception('Invalid request method.');
        }
    }

    private function safeDiagnostics()
    {
        try {
            return $this->supervisor->diagnostics();
        } catch (Exception $e) {
            $diagnostics = $this->fallbackDiagnostics();
            $diagnostics['error'] = $e->getMessage();

            return $diagnostics;
        }
    }

    private function configExistsMap(array $programs)
    {
        $map = array();
        foreach ($programs as $program) {
            $map[$program['id']] = $this->supervisor->configExists($program);
        }

        return $map;
    }

    private function fallbackDiagnostics()
    {
        $os = $this->readOsRelease();
        $installCommand = $this->installCommandForOs($os['id']);
        $supervisorctl = $this->findExecutable(array('/usr/bin/supervisorctl', '/usr/local/bin/supervisorctl', '/bin/supervisorctl'));

        return array(
            'installed' => $supervisorctl !== null,
            'supervisorctl' => $supervisorctl,
            'os' => $os['pretty_name'],
            'os_id' => $os['id'],
            'config_dir' => is_dir('/etc/supervisord.d') && !is_dir('/etc/supervisor/conf.d') ? '/etc/supervisord.d' : '/etc/supervisor/conf.d',
            'main_config' => file_exists('/etc/supervisor/supervisord.conf') ? '/etc/supervisor/supervisord.conf' : (file_exists('/etc/supervisord.conf') ? '/etc/supervisord.conf' : null),
            'service' => file_exists('/etc/systemd/system/supervisord.service') || file_exists('/usr/lib/systemd/system/supervisord.service') ? 'supervisord' : 'supervisor',
            'install_command' => $installCommand,
            'supports_install' => $installCommand !== null,
        );
    }

    private function readOsRelease()
    {
        $os = array(
            'id' => 'unknown',
            'pretty_name' => php_uname('s'),
        );

        if (!is_readable('/etc/os-release')) {
            return $os;
        }

        foreach (file('/etc/os-release') as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '=') === false) {
                continue;
            }

            list($key, $value) = explode('=', $line, 2);
            $value = trim($value, "\"'");
            if ($key === 'ID') {
                $os['id'] = strtolower($value);
            } elseif ($key === 'PRETTY_NAME') {
                $os['pretty_name'] = $value;
            }
        }

        return $os;
    }

    private function installCommandForOs($osId)
    {
        if (in_array($osId, array('ubuntu', 'debian'), true)) {
            return 'DEBIAN_FRONTEND=noninteractive apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -y supervisor';
        }
        if (in_array($osId, array('centos', 'rhel', 'almalinux', 'rocky', 'fedora'), true)) {
            return '(command -v dnf >/dev/null 2>&1 && dnf install -y supervisor) || yum install -y supervisor';
        }

        return null;
    }

    private function findExecutable(array $paths)
    {
        foreach ($paths as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        return null;
    }

    private function currentDomainId()
    {
        $siteId = $this->requestValue('site_id');
        if ($siteId !== null && $siteId !== '') {
            return $this->normalizeDomainId($siteId);
        }

        if (!$this->_request->isPost()) {
            $domainId = $this->requestValue('domain_id');
            if ($domainId !== null && $domainId !== '') {
                return $this->normalizeDomainId($domainId);
            }
        }

        $contextDomainId = $this->_request->getPost('context_domain_id');
        if ($contextDomainId !== null && $contextDomainId !== '') {
            return $this->normalizeDomainId($contextDomainId);
        }

        $domId = $this->requestValue('dom_id');
        if ($domId !== null && $domId !== '') {
            return $this->resolveSubscriptionDomainId($domId);
        }

        return null;
    }

    private function requestValue($name)
    {
        $value = $this->_request->getParam($name);
        return $value !== null && $value !== '' ? $value : null;
    }

    private function normalizeDomainId($domainId)
    {
        try {
            $domain = new pm_Domain((int) $domainId);
            return (int) $domain->getId();
        } catch (Exception $e) {
            return null;
        }
    }

    private function resolveSubscriptionDomainId($subscriptionId)
    {
        $directDomainId = $this->normalizeDomainId($subscriptionId);
        if ($directDomainId !== null) {
            return $directDomainId;
        }

        try {
            foreach (pm_Domain::getAllDomains(false) as $domain) {
                if (!$domain->hasHosting()) {
                    continue;
                }
                foreach (array('webspace_id', 'parentDomainId') as $property) {
                    try {
                        if ((int) $domain->getProperty($property) === (int) $subscriptionId) {
                            return (int) $domain->getId();
                        }
                    } catch (Exception $e) {
                    }
                }
            }
        } catch (Exception $e) {
        }

        return null;
    }

    private function requireProgramInCurrentDomain(array $program)
    {
        $domainId = $this->currentDomainId();
        if ($domainId !== null && $domainId !== '' && !$this->store->belongsToDomainContext($program, $domainId)) {
            throw new pm_Exception('Program does not belong to the selected domain.');
        }
    }

    private function domainQuery()
    {
        $domainId = $this->currentDomainId();
        if ($domainId === null || $domainId === '') {
            return '';
        }

        return '?site_id=' . urlencode($domainId);
    }

    private function domainParamName()
    {
        return 'site_id';
    }

    private function domainName($domainId)
    {
        if ($domainId === null || $domainId === '') {
            return null;
        }

        $domains = $this->store->domains();
        return isset($domains[(int) $domainId]) ? $domains[(int) $domainId] : null;
    }

    private function redirectWithNotice($message)
    {
        $this->pushFlash('notice', $message);
        return $this->redirectToIndex();
    }

    private function redirectWithError($message)
    {
        $this->pushFlash('error', $message);
        return $this->redirectToIndex();
    }

    private function redirectToIndex()
    {
        $this->_helper->viewRenderer->setNoRender(true);
        $this->_helper->layout()->disableLayout();
        $this->getResponse()->setRedirect(pm_Context::getBaseUrl() . 'index.php/index/index' . $this->domainQuery());
    }

    private function shortenMessage($message)
    {
        $message = trim((string) $message);
        if (strlen($message) > 900) {
            return substr($message, 0, 900) . '...';
        }

        return $message;
    }

    private function pushFlash($type, $message)
    {
        $file = pm_Context::getVarDir() . '/data/flash.json';
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        file_put_contents($file, json_encode(array(
            $type => $this->shortenMessage($message),
        ), JSON_PRETTY_PRINT), LOCK_EX);
        chmod($file, 0640);
    }

    private function pullFlash()
    {
        $file = pm_Context::getVarDir() . '/data/flash.json';
        if (!file_exists($file)) {
            return array();
        }

        $data = json_decode(file_get_contents($file), true);
        unlink($file);

        return is_array($data) ? $data : array();
    }
}
