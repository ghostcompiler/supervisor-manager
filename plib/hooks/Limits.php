<?php

require_once pm_Context::getPlibDir() . '/library/SupervisorManager/Permissions.php';

class Modules_SupervisorManager_Limits extends pm_Hook_Limits
{
    public function getLimits()
    {
        return array(
            SupervisorManager_Permissions::LIMIT_PROGRAMS => array(
                'default' => 0,
                'place' => self::PLACE_ADDITIONAL,
                'name' => 'Maximum Supervisor programs',
                'description' => 'Maximum number of Supervisor programs that can be created for a subscription. Use -1 for unlimited.',
            ),
        );
    }
}
