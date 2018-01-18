<?php
namespace LdapUtility;

use LdapUtility\Exception\LdapException;

/**
 * class LDAP
 */
class Ldap
{
    /**
     * Default config for LDAP
     *
     * -`host` - LDAP host name
     * -`port` - port to connect - defaults to 389
     * -`protocol_version` - LDAP protocol version, defaults to 3
     * -`baseDN` - base DN
     * -`startTLS` - bool value whether to est connection with TLS
     * -`commonBindDn` - common bind credential to use search feature
     * -`commonBindPassword` - common bind password
     * -`hideErrors` - bool value whether to suppress errors and warnings - defaults to false
     */
    protected $defaultConfig = [
        'host' => '',
        'port' => 389,
        'protocol_version' => 3,
        'baseDn' => '',
        'startTLS' => false,
        'commonBindDn' => '',
        'commonBindPassword' => '',
        'hideErrors' => false
    ];


    protected $ldapConnection = null;
    protected $bound = false;
    protected $config = [];

    /**
     * Constructor
     * @param array $config - Config
     * @return void
     */
    public function __construct($config)
    {
        $this->config($config);
        $this->connect();
        $this->setProtocolVersion();
        $this->setTLS();
        $this->setOption(LDAP_OPT_DEBUG_LEVEL, 7);
    }

    /**
     * set ldap config
     *
     * @param array $config Config
     * @return array $config merged config
     */
    protected function config($config)
    {
        return $this->config = array_merge($this->defaultConfig, $config);
    }

    /**
     * Connect ldap server
     * @return void
     */
    protected function connect()
    {
        $this->ldapConnection = ldap_connect($this->config['host'], $this->config['port']);
    }

    /**
     * Set LDAP protocol version - defaults to version 3
     * @return void
     */
    protected function setProtocolVersion()
    {
        $this->setOption(LDAP_OPT_PROTOCOL_VERSION, $this->config['protocol_version']);
    }

    /**
     * Set option for LDAP connection
     *
     * @param int $key LDAP predefined constant option
     * @param mixed $value value to set
     * @return bool success
     */
    public function setOption($key, $value)
    {
        if ($this->config['hideErrors']) {
            return @ldap_set_option($this->ldapConnection, $key, $value);
        }

        return ldap_set_option($this->ldapConnection, $key, $value);
    }

    /**
     * Set startTLS config
     * @return void
     */
    protected function setTLS()
    {
        if (!$this->config['startTLS']) {
            return;
        }

        if ($this->config['hideErrors']) {
            return @ldap_start_tls($this->ldapConnection);
        }

        return ldap_start_tls($this->ldapConnection);
    }

    /**
     * LDAP bind using credentials
     *
     * @param string $bindDn - the distinguished name
     * @param string $password - password
     * @return void
     * @throws LdapException on connection/bind failure
     */
    public function bindUsingCredentials($bindDn, $password)
    {
        if ($this->config['hideErrors']) {
            $this->bound = @ldap_bind($this->ldapConnection, $bindDn, $password);
        } else {
            $this->bound = ldap_bind($this->ldapConnection, $bindDn, $password);
        }

        if (!$this->bound) {
            $this->throwExceptionOnErrors();
        }
    }

    /**
     * LDAP bind using common bind credentials
     *
     * @return void
     */
    public function bindUsingCommonCredentials()
    {
        $bindDn = $this->config['commonBindDn'];
        $password = $this->config['commonBindPassword'];
        $this->bindUsingCredentials($bindDn, $password);
    }

    /**
     * Authenticate user with ldap
     *
     * @param string $username Username
     * @param string $password Password
     * @return array|false Ldap result or false if user not found
     */
    public function authenticateUser($username, $password)
    {
        $suffix = '';
        if ($this->checkForRoleSuffix($username)) {
            $explodedUsername = explode('+', $username);
            $username = $explodedUsername[0];
            $suffix = $explodedUsername[1];
        }
        $bindDn = $this->getBindDn($username);
        try {
            $this->bindUsingCredentials($bindDn, $password);
            $rDn = $this->getRelativeDn($username);
            $user = $this->find('read', [
                'baseDn' => $bindDn,
                'filter' => $rDn,
                'attributes' => ['cn', 'sn', 'mail']
            ]);

            if ($user['count'] == 0) {
                return false;
            }

            if (!empty($suffix)) {
                $user['role_suffix'] = $suffix;
            }

            return $user;
        } catch (LdapException $e) {
            return false;
        }
    }

    /**
     * Bind dn from config fields and username
     *
     * @param string $username username
     * @return string $bindDn Bind dn
     */
    public function getBindDn($username)
    {
        $bindDn = $this->formatString($this->config['auth']['bindDn'], $username);

        return $bindDn;
    }

    /**
     * relative dn from config fields and username
     *
     * @param string $username username
     * @return string $rDn relative dn
     */
    public function getRelativeDn($username)
    {
        $rDn = $this->formatString($this->config['auth']['searchFilter'], $username);

        return $rDn;
    }

    /**
     * Replace the username tag in string with user name
     * @param  [type] $string   [description]
     * @param  [type] $username [description]
     * @return [type]           [description]
     */
    protected function formatString($string, $username)
    {
        return str_replace('{username}', $username, $string);
    }

    /**
     * Check if username has any valid suffix role
     *
     * @param string $username username
     * @return bool success
     */
    protected function checkForRoleSuffix($username)
    {
        return (preg_match('/^[a-zA-Z0-9]+\.[a-zA-Z0-9]+\+[a-zA-Z0-9]+$/', $username));
    }

    /**
     * Add suffix to email mailbox with +
     *
     * @param string $email Email
     * @param string $suffix Suffix to add
     * @return string $resultEmail result email
     */
    public function addSuffixToMailbox($email, $suffix)
    {
        $explodedMail = explode('@', $email);

        return ($explodedMail[0] . '+' . $suffix . '@' . $explodedMail[1]);
    }

    /**
     * Find user from ldap
     *
     * @param string $searchType read|search
     * @param array $options search options
     * @return array $result
     */
    public function find($searchType, $options)
    {
        if (!$this->bound) {
            throw new LdapException("Unable to find server binding... ");
        }
        $resultIdentifier = $this->getResultIdentifier($searchType, $options);
        $rawResult = $this->getAllEntries($resultIdentifier);

        return $rawResult;
    }

    /**
     * Result identifier
     * @param string $searchType read|search
     * @param array $options search options
     * @return false|resource $resultIdentifier Identifier
     */
    public function getResultIdentifier($searchType, $options)
    {
        $baseDn = $options['baseDn'] ?? $this->config['baseDn'];
        if (!isset($options['filter'])) {
            throw new LdapException('Filter field not set in search');
        }
        if (!isset($options['attributes'])) {
            $options['attributes'] = [];
        }

        if ($searchType == 'search') {
            $result = $this->search($baseDn, $options['filter'], $options['attributes']);
        } elseif ($searchType == 'read') {
            $result = $this->read($baseDn, $options['filter'], $options['attributes']);
        } else {
            throw new LdapException('Unknown search type - ' . $searchType);
        }

        if ($result === false) {
            $this->throwExceptionOnErrors();
        }

        return $result;
    }

    /**
     * Read an entry in ldap directory
     *
     * @param string $baseDn base dn
     * @param string $filter filter string to search
     * @param array $attributes attributes to return
     * @return false|resource identifier
     */
    public function read($baseDn, $filter, $attributes)
    {
        if ($this->config['hideErrors']) {
            return @ldap_read($this->ldapConnection, $baseDn, $filter, $attributes);
        }

        return ldap_read($this->ldapConnection, $baseDn, $filter, $attributes);
    }

    /**
     * Search for filter in ldap directory
     *
     * @param string $baseDn base dn
     * @param string $filter filter string to search
     * @param array $attributes attributes to return
     * @return false|resource identifier
     */
    public function search($baseDn, $filter, $attributes)
    {
        if ($this->config['hideErrors']) {
            return @ldap_search($this->ldapConnection, $baseDn, $filter, $attributes);
        }

        return ldap_search($this->ldapConnection, $baseDn, $filter, $attributes);
    }

    /**
     * Get all entries based on entry identifier
     *
     * @param entry_identifier $entryId Entry identifier
     * @return array $result entries
     */
    public function getAllEntries($entryId)
    {
        if ($this->config['hideErrors']) {
            return @ldap_get_entries($this->ldapConnection, $entryId);
        }

        return ldap_get_entries($this->ldapConnection, $entryId);
    }

    /**
     * LDAP connection - resource link identifier
     * @return resource $ldapConnection
     */
    public function getConnection()
    {
        return $this->ldapConnection;
    }

    /**
     * Convert LDAP error into exceptions
     *
     * @return void
     * @throws LdapUtility\Exception\LdapException on LDAP error
     */
    public function throwExceptionOnErrors()
    {
        $errorNo = $this->getErrorNo();
        if ($errorNo !== 0) {
            throw new LdapException($this->getError(), $errorNo);
        }
    }

    /**
     * Get LDAP error code
     * @return int $errorCode LDAP error code
     */
    public function getErrorNo()
    {
        return ldap_errno($this->ldapConnection);
    }

    /**
     * Get LDAP error message
     * @return string $error LDAP error message
     */
    public function getError()
    {
        return ldap_error($this->ldapConnection);
    }

    /**
     * Get base dn from config
     *
     * @return string $baseDn base dn
     */
    public function getBaseDn()
    {
        return $this->config['baseDn'];
    }

    /**
     * bound status
     * @return bool success
     */
    public function getBound()
    {
        return $this->bound;
    }

    /**
     * Close existing LDAP connection
     * @return bool success
     */
    public function close()
    {
        return ldap_close($this->ldapConnection);
    }
}
