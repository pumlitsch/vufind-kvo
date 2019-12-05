<?php

namespace knav\Auth;

class LDAP extends \VuFind\Auth\LDAP
{

	/**
     * Establish the LDAP connection.
     *
     * @return resource
     */
    protected function connect()
    {
        // Try to connect to LDAP and die if we can't
        $host = $this->getSetting('host');
        $port = $this->getSetting('port');
        $this->debug("connecting to host=$host, port=$port");
        $connection = @ldap_connect($host, $port);
        if (!$connection) {
            $this->debug('connection failed');
            throw new AuthException('authentication_error_technical');
        }

        // Set LDAP options -- use protocol version 3
        if (!@ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3)) {
            $this->debug('Failed to set protocol version 3');
        }

        // if the host parameter is not specified as ldaps://
        // then we need to initiate TLS so we
        // can have a secure connection over the standard LDAP port.

        if (stripos($host, 'ldaps://') === false) {
            $this->debug('Starting TLS');
            if (!@ldap_start_tls($connection)) {
                $this->debug('TLS failed');
                throw new AuthException('authentication_error_technical');
            }
        }

        return $connection;
    }


    /**
     * Validate credentials
     *
     * @param resource $connection LDAP connection
     * @param array    $info       Data from findUsername()
     * @param string   $password   Password to try
     *
     * @return bool|array Array of user data on success, false otherwise
     */
    protected function validateCredentialsInLdap($connection, $info, $password)
    {

        // Validate the user credentials by attempting to bind to LDAP:
        $dn = $info[0]['dn'];
        $this->debug("binding as $dn");
        $ldapBind = @ldap_bind($connection, $dn, strtoupper($password));
        if (!$ldapBind) {
            $this->debug('bind failed -- ' . ldap_error($connection));
            return false;
        }
        // If the bind was successful, we can look up the full user info:
        $this->debug('bind successful; reading details');
        $ldapSearch = ldap_read($connection, $dn, 'objectclass=*');
        $data = ldap_get_entries($connection, $ldapSearch);
        if ($data === false) {
            $this->debug('Read failed -- ' . ldap_error($connection));
            throw new AuthException('authentication_error_technical');
        }
        return $data;
    }


    /**
     * Build a User object from details obtained via LDAP.
     *
     * @param string $username Username
     * @param array  $data     Details from ldap_get_entries call.
     *
     * @return \VuFind\Db\Row\User Object representing logged-in user.
     */
    protected function processLDAPUser($username, $data)
    {
        // Database fields that we may be able to load from LDAP:
        $fields = [
            'firstname', 'lastname', 'email', 'cat_username', 'cat_password',
            'college', 'major'
        ];

        $user = $this->getUserTable()->getByUsername($username);
        $catPassword = null;

        // Loop through LDAP response and map fields to database object based
        // on configuration settings:
        for ($i = 0; $i < $data["count"]; $i++) {
            for ($j = 0; $j < $data[$i]["count"]; $j++) {
                foreach ($fields as $field) {
                    $configValue = $this->getSetting($field);
                    if ($data[$i][$j] == $configValue && !empty($configValue)) {
                        $value = $data[$i][$configValue][0];
                        $this->debug("found $field = $value");
                        if ($field != "cat_password") {
                            $user->$field = $value;
                        } else {
                            $catPassword = $value;
                        }
                    }
                }
            }
        }
        
        if (!empty($user->cat_username)) {
            $user->saveCredentials(
                $user->cat_username,
                empty($catPassword) ? $user->getCatPassword() : $catPassword
            );
        }

        $user->save();
        return $user;
    }

}

