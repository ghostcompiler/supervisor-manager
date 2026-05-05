<?php

class Modules_SupervisorManager_Navigation extends pm_Hook_Navigation
{
    public function getNavigation()
    {
        return array(
            array(
                'controller' => 'index',
                'action' => 'index',
                'label' => 'Supervisor Manager',
                'pages' => array(
                    array(
                        'controller' => 'index',
                        'action' => 'add',
                        'label' => 'Add Program',
                    ),
                    array(
                        'controller' => 'index',
                        'action' => 'edit',
                        'label' => 'Edit Program',
                    ),
                    array(
                        'controller' => 'index',
                        'action' => 'logs',
                        'label' => 'Logs',
                    ),
                ),
            ),
        );
    }
}
