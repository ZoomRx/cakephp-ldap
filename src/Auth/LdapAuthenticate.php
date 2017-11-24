<?php
namespace LdapUtility\Auth;

use Cake\Auth\BaseAuthenticate;
use Cake\Controller\ComponentRegistry;
use Cake\Network\Request;
use Cake\Network\Response;
use Cake\ORM\TableRegistry;
use LdapUtility\Exception\LdapException;
use LdapUtility\Ldap;

/**
 * LDAP authentication adapter for AuthComponent
 *
 * Provides LDAP authentication for given username and password
 *
 * ## usage
 * Add LDAP auth to controllers component
 */
class LdapAuthenticate extends BaseAuthenticate
{
    protected $ldap = null;

    /**
     * Constructor
     *
     * Auth settings
     * -`host` - LDAP host name
     * -`port` - port to connect - defaults to 389
     * -`protocol_version` - LDAP protocol version, defaults to 3
     * -`baseDN` - base DN
     * -`startTLS` - bool value whether to est connection with TLS
     * -`hideErrors` - bool value whether to suppress errors and warnings - defaults to false
     * -`queryDatasource` - boolean to decide whether to query app datasource after ldap authentication
          defaults to true
     * -`userModel` - If `queryDatasource` is set, table name to query. defaults to Users
     * -`fields.username` - The field to query on the userModel. defaults to email
     * {@inheritDoc}
     */
    public function __construct(ComponentRegistry $registry, array $config = [])
    {
        $this->config([
            'host' => '',
            'port' => 389,
            'protocol_version' => 3,
            'baseDn' => '',
            'startTLS' => false,
            'hideErrors' => false,
            'queryDatasource' => true,
            'userModel' => 'Users',
            'fields' => ['username' => 'email'],
            'auth' => [
                'searchFilter' => '',
                'bindDn' => ''
            ]
        ]);
        parent::__construct($registry, $config);

        $this->ldap = new ldap($config);
    }

    /**
     * Authenticate user
     *
     * {@inheritDoc}
     */
    public function authenticate(Request $request, Response $response)
    {
        if (empty($request->data['username']) || empty($request->data['password'])) {
            throw new LdapException('Empty username or password');
        }

        return $this->_findUser($request->data['username'], $request->data['password']);
    }

    /**
     * Find user method
     *
     * @param string $username Username
     * @param string $password Password
     * @return bool|array
     */
    protected function _findUser($username, $password = null)
    {
        $ldapUserDetails = $this->ldap->authenticateUser($username, $password);

        if (!$ldapUserDetails || empty($ldapUserDetails[0]['mail'][0])) {
            return false;
        }

        if (!$this->_config['queryDatasource']) {
            return $ldapUserDetails;
        }

        if (!empty($ldapUserDetails['role_suffix'])) {
            $userEmail = $this->ldap->addSuffixToMailbox($ldapUserDetails[0]['mail'][0], $ldapUserDetails['role_suffix']);
        } else {
            $userEmail = $ldapUserDetails[0]['mail'][0];
        }

        $user = parent::_findUser($userEmail);
        $callback = $this->_config['auth']['callback'] ?? '';
        if (!empty($callback)) {
            $user = TableRegistry::get($this->_config['userModel'])->$callback($ldapUserDetails, $user);
        }
        if (!empty($user)) {
            $user['ldap_cn'] = $ldapUserDetails[0]['cn'][0];
        }

        return $user;
    }

    /**
     * Destructor
     * Close LDAP connection if any
     *
     * @return void
     */
    public function __destruct()
    {
        if (!empty($this->ldap) && method_exists($this->ldap, 'close')) {
            $this->ldap->close();
        }
    }
}
