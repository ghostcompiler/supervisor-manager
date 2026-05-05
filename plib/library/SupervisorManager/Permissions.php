<?php

class SupervisorManager_Permissions
{
    const ACCESS = 'access_supervisor_manager';
    const MANAGE = 'manage_supervisor_programs';
    const CONTROL = 'control_supervisor_programs';
    const LOGS = 'view_supervisor_logs';
    const LIMIT_PROGRAMS = 'max_supervisor_programs';

    public static function can($permission, $domainId)
    {
        $client = self::currentClient();
        if ($client->isAdmin()) {
            return true;
        }

        if ($domainId === null || $domainId === '') {
            return false;
        }

        try {
            $domainId = (int) $domainId;
            $domain = new pm_Domain($domainId);
            if (!self::hasDomainAccess($client, $domain)) {
                return false;
            }
        } catch (Exception $e) {
            return false;
        }

        if (!self::hasEffectivePermission($client, $domain, self::ACCESS)) {
            return false;
        }

        if ($permission === self::ACCESS) {
            return true;
        }

        return self::hasEffectivePermission($client, $domain, $permission);
    }

    public static function canAny($permission)
    {
        if (self::isAdmin()) {
            return true;
        }

        foreach (self::accessibleDomains() as $domain) {
            if (!$domain->hasHosting()) {
                continue;
            }
            if (self::can($permission, $domain->getId())) {
                return true;
            }
        }

        return false;
    }

    public static function isAdmin()
    {
        try {
            $client = self::currentClient();
            return $client && method_exists($client, 'isAdmin') && $client->isAdmin();
        } catch (Exception $e) {
            return false;
        }
    }

    public static function filterDomains(array $domains, $permission)
    {
        if (self::currentClient()->isAdmin()) {
            return $domains;
        }

        $filtered = array();
        foreach ($domains as $id => $name) {
            if (self::can($permission, $id)) {
                $filtered[$id] = $name;
            }
        }

        return $filtered;
    }

    public static function accessibleDomains()
    {
        $client = self::currentClient();
        if ($client->isAdmin()) {
            return pm_Domain::getAllDomains(false);
        }

        $domains = array();
        try {
            foreach (pm_Session::getCurrentDomains() as $domain) {
                if (self::hasDomainAccess($client, $domain)) {
                    $domains[$domain->getId()] = $domain;
                }
            }
        } catch (Exception $e) {
        }

        try {
            $domain = pm_Session::getCurrentDomain();
            if (self::hasDomainAccess($client, $domain)) {
                $domains[$domain->getId()] = $domain;
            }
        } catch (Exception $e) {
        }

        try {
            foreach (pm_Domain::getDomainsByClient($client, false) as $domain) {
                if (self::hasDomainAccess($client, $domain)) {
                    $domains[$domain->getId()] = $domain;
                }
            }
        } catch (Exception $e) {
        }

        try {
            foreach (pm_Domain::getAllDomains(false) as $domain) {
                try {
                    if (self::hasDomainAccess($client, $domain)) {
                        $domains[$domain->getId()] = $domain;
                    }
                } catch (Exception $e) {
                }
            }
        } catch (Exception $e) {
        }

        return $domains;
    }

    public static function filterPrograms(array $programs, $permission)
    {
        if (self::currentClient()->isAdmin()) {
            return $programs;
        }

        $filtered = array();
        foreach ($programs as $program) {
            if (self::can($permission, $program['domain_id'])) {
                $filtered[] = $program;
            }
        }

        return $filtered;
    }

    public static function assertDomain($permission, $domainId)
    {
        if (!self::can($permission, $domainId)) {
            throw new pm_Exception('Supervisor Manager is not enabled for this domain or action.');
        }
    }

    public static function assertProgram(array $program, $permission)
    {
        self::assertDomain($permission, $program['domain_id']);
    }

    public static function assertProgramLimit($domainId, $programCount)
    {
        if (self::currentClient()->isAdmin()) {
            return;
        }

        if (self::hasCapacity($domainId, $programCount)) {
            return;
        }

        throw new pm_Exception('Supervisor program limit reached for this domain.');
    }

    public static function hasCapacity($domainId, $programCount)
    {
        if (self::currentClient()->isAdmin()) {
            return true;
        }

        try {
            $domain = new pm_Domain((int) $domainId);
            $limit = self::normalizeLimit($domain->getLimit(self::LIMIT_PROGRAMS));
        } catch (Exception $e) {
            return false;
        }

        return $limit < 0 || (int) $programCount < $limit;
    }

    public static function currentClient()
    {
        try {
            if (
                method_exists('pm_Session', 'isImpersonated') &&
                method_exists('pm_Session', 'getImpersonatedClientId') &&
                pm_Session::isImpersonated()
            ) {
                return pm_Client::getByClientId(pm_Session::getImpersonatedClientId());
            }
        } catch (Exception $e) {
        }

        return pm_Session::getClient();
    }

    public static function hasDomainAccess($client, $domain)
    {
        if ($client->isAdmin()) {
            return true;
        }

        try {
            if ($client->hasAccessToDomain($domain->getId())) {
                return true;
            }
        } catch (Exception $e) {
        }

        try {
            $owner = $domain->getClient();
            if ((int) $owner->getId() === (int) $client->getId()) {
                return true;
            }

            if ($client->isReseller()) {
                try {
                    if ((int) $owner->getProperty('vendor_id') === (int) $client->getId()) {
                        return true;
                    }
                } catch (Exception $e) {
                }
            }
        } catch (Exception $e) {
        }

        return false;
    }

    private static function hasEffectivePermission($client, $domain, $permission)
    {
        if (
            self::hasClientPermission($client, $permission, $domain) ||
            self::hasPlanPermission($domain, $permission)
        ) {
            return true;
        }

        return self::hasSupervisorResourceGrant($client, $domain, $permission);
    }

    private static function hasPlanPermission($domain, $permission)
    {
        try {
            return self::normalizeBoolean($domain->hasPermission($permission));
        } catch (Exception $e) {
            return false;
        }
    }

    private static function hasClientPermission($client, $permission, $domain)
    {
        if ($domain === null) {
            return false;
        }

        try {
            return self::normalizeBoolean($client->hasPermission($permission, $domain));
        } catch (Exception $e) {
            return false;
        }
    }

    private static function hasSupervisorResourceGrant($client, $domain, $permission)
    {
        if ($permission !== self::MANAGE) {
            return false;
        }

        if (
            !self::hasClientPermission($client, self::ACCESS, $domain) &&
            !self::hasPlanPermission($domain, self::ACCESS)
        ) {
            return false;
        }

        try {
            return self::normalizeLimit($domain->getLimit(self::LIMIT_PROGRAMS)) !== 0;
        } catch (Exception $e) {
            return false;
        }
    }

    private static function normalizeLimit($value)
    {
        if (is_int($value)) {
            return $value;
        }

        $value = strtolower(trim((string) $value));
        if ($value === '' || $value === 'false') {
            return 0;
        }
        if ($value === 'unlimited' || $value === '-1') {
            return -1;
        }

        return (int) $value;
    }

    private static function normalizeBoolean($value)
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value !== 0;
        }

        $value = strtolower(trim((string) $value));
        return in_array($value, array('1', 'true', 'on', 'yes', 'enabled'), true);
    }
}
