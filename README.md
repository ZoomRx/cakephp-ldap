# Cakephp-ldap plugin for CakePHP

## Requirements

* CakePHP 3.0+

## Installation

You can install this plugin into your CakePHP application using [composer](http://getcomposer.org).

The recommended way to install composer packages is:

```
composer require zoomrx/cakephp-ldap
```

## Usage

In your app's `config/bootstrap.php` add:

```php
// In config/bootstrap.php
Plugin::load('LdapUtility');
```

or using cake's console:

```sh
./bin/cake plugin load LdapUtility
```

## Configuration:

Basic configuration for creating ldap handler instance

```php
	$config = [
		'host' => 'ldap.example.com',
        'port' => 389,
        'baseDn' => 'dc=example,dc=com',
        'startTLS' => true,
        'hideErrors' => true,
        'commonBindDn' => 'cn=readonly.user,ou=people,dc=example,dc=com',
        'commonBindPassword' => 'secret'
	]
	$ldapHandler = new LdapUtility\Ldap($config);
```

#### Config parameters

| Parameter | Description |
| --------- | ----------- |
| `host` | Host name of LDAP server |
| `port` | Port to connect with LDAP server. Defaults to 389 |
| `baseDn` | Base Distinguished name (DN) |
| `startTLS` | Boolean to decide on connection with/without TLS. Defaults to false|
| `hideErrors` | Boolean to show/hide LDAP errors. Defaults to false |
| `commonBindDn` | Common bind DN. Used in the case of readonly operations |
| `commonBindPassword` | Password for common bind DN |



#### Setup Ldap authentication config in Controller

Parameters for setting LDAP authentication has all the parameters of LDAP handler connection except commonBindDn and commonBindPassowrd

```php
    // In your controller, for e.g. src/Api/UsersController.php
    public function initialize()
    {
        parent::initialize();

        $this->loadComponent('Auth', [
            'storage' => 'Memory',
            'authenticate', [
                'LdapUtility.Ldap' => [
					'host' => 'ldap.example.com',
			        'port' => 389,
			        'baseDn' => 'dc=example,dc=com',
			        'startTLS' => true,
			        'hideErrors' => true,
			        'queryDatasource' => true,
                    'userModel' => 'Users',
                    'fields' => ['username' => 'email'],
                    'auth' => [
		                'searchFilter' => '(cn={username})',
		                'bindDn' => 'cn={username},ou=people,dc=example,dc=com'
		            ]
				]
            ],

            'unauthorizedRedirect' => false,
            'checkAuthIn' => 'Controller.initialize',
        ]);
    }
```

#### Authentication specific configs

| Parameter | Description |
| --------- | ----------- |
| `auth.searchFilter` | Search filter syntax with username placeholder. The placeholder will be replaced by username data from request. This is used to read LDAP data entry of the authenticated user |
| `auth.bindDn` | Bind DN syntax with username placeholder between braces. The placeholder will be replaced by username data from request |
| `auth.callback` | Callback function that will execute after fetching details from app datasource. Both LDAP details array and app user details array will be passed as arguments. If user has no record in app datasource, user details array will be false. Callback will be called only if queryDatasource is true |
| `queryDataSource` | Boolean to decide whether to query app datasource after successful LDAP authentication |
| `userModel` | If queryDataSource is set, userModel table will be used for base authentication |
| `fields.username` | If queryDataSource is set, authenticate class will use field.username as field condition for base authentication |


## Examples:

Search for entry with cn starting with test
```php
	$ldapHandler->find('search', [
		'baseDn' => 'ou=people,dc=example,dc=com',
		'filter' => 'cn=test*',
		'attributes' => ['cn', 'sn', 'mail']
	]);
```

Read a particular entry with cn=test.user
```php
	$ldapHandler->find('read', [
		'baseDn' => 'ou=people,dc=example,dc=com',
		'filter' => 'cn=test.user',
		'attributes' => ['cn', 'sn', 'mail']
	]);
```

## TLS connections in development environment
	
	To connect an LDAP server over TLS connection, check ldap.conf file
		* For mac, conf file is located in /etc/openldap/ldap.conf
		* For unix, conf file is located in /etc/ldap/ldap.conf 
	To disable certificate verification change TLS_REQCERT to 'never' in ldap.conf file
