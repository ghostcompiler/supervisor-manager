<?php
pm_Context::init('supervisor-manager');

$dataDir = pm_Context::getVarDir() . '/data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0750, true);
}

$programsFile = $dataDir . '/programs.json';
if (!file_exists($programsFile)) {
    file_put_contents($programsFile, json_encode(array(), JSON_PRETTY_PRINT));
    chmod($programsFile, 0640);
}

$moduleId = pm_Context::getModuleId();
$helpers = array(
    '/usr/local/psa/admin/bin/modules/' . $moduleId . '/supervisor-manager',
    '/usr/local/psa/admin/bin/modules/' . $moduleId . '/supervisor-manager.php',
    '/usr/local/psa/admin/sbin/modules/' . $moduleId . '/supervisor-manager',
    '/usr/local/psa/admin/sbin/modules/' . $moduleId . '/supervisor-manager.php',
);

foreach ($helpers as $helper) {
    if (file_exists($helper)) {
        chmod($helper, 0755);
    }
}
