<?php

require_once pm_Context::getPlibDir() . '/library/SupervisorManager/Permissions.php';

class Modules_SupervisorManager_Permissions extends pm_Hook_Permissions
{
    public function getPermissions()
    {
        return array(
            SupervisorManager_Permissions::ACCESS => array(
                'default' => false,
                'place' => self::PLACE_ADDITIONAL,
                'name' => 'Supervisor Manager access',
                'description' => 'Allow access to assigned Supervisor programs in Plesk.',
            ),
            SupervisorManager_Permissions::CONTROL => array(
                'default' => false,
                'place' => self::PLACE_ADDITIONAL,
                'name' => 'Control Supervisor programs',
                'description' => 'Allow start, stop, and restart actions for assigned Supervisor programs.',
                'master' => SupervisorManager_Permissions::ACCESS,
            ),
            SupervisorManager_Permissions::LOGS => array(
                'default' => false,
                'place' => self::PLACE_ADDITIONAL,
                'name' => 'View Supervisor logs',
                'description' => 'Allow viewing live logs for assigned Supervisor programs.',
                'master' => SupervisorManager_Permissions::ACCESS,
            ),
            SupervisorManager_Permissions::MANAGE => array(
                'default' => false,
                'place' => self::PLACE_ADDITIONAL,
                'name' => 'Manage Supervisor programs',
                'description' => 'Allow creating, editing, deleting, and regenerating Supervisor configs for assigned domains.',
                'master' => SupervisorManager_Permissions::ACCESS,
            ),
        );
    }
}
