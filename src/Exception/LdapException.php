<?php
namespace LdapUtility\Exception;

use RuntimeException;

class LdapException extends RuntimeException
{
    /**
     * Constructor
     *
     * @param string $message message
     * @param int $code Ldap error code
     * @param \Exception $previous previous exception passed
     * @return void
     */
    public function __construct($message = '', $code = 1, \Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
