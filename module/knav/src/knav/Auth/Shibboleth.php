<?php

namespace knav\Auth;

/**
 * Shibboleth authentication module.
 */
class Shibboleth extends \VuFind\Auth\Shibboleth
{
    const DEFAULT_IDPSERVERPARAM = 'Shib-Identity-Provider';

    /**
     * Validate configuration parameters.
     *
     * @throws AuthException
     * @return void
     */
    protected function validateConfig()
    {
        $shib = $this->config->Shibboleth;
        if (!isset($shib->username) || empty($shib->username)) {
            throw new AuthException(
                "Shibboleth username is missing in your configuration file."
            );
        }

        if (!isset($shib->login)) {
            throw new AuthException(
                'Shibboleth login configuration parameter is not set.'
            );
        }
    }

    /**
     * Attempt to authenticate the current user.  Throws exception if login fails.
     *
     * @param \Zend\Http\PhpEnvironment\Request $request Request object containing
     * account credentials.
     *
     * @throws AuthException
     * @return \VuFind\Db\Row\User Object representing logged-in user.
     */
    public function authenticate($request)
    {
        $shib = $this->getConfig()->Shibboleth;
        $username = $request->getServer()->get($shib->username);
        if (empty($username)) {
            throw new AuthException('authentication_error_admin');
        }

        foreach ($this->getRequiredAttributes() as $key => $value) {
            if (!preg_match('/' . $value . '/', $request->getServer()->get($key))) {
                throw new AuthException('authentication_error_denied');
            }
        }

        $user = $this->getUserTable()->getByUsername($username);

        $catPassword = null;

        $attribsToCheck = [
            'cat_username', 'cat_password', 'email', 'lastname', 'firstname',
            'college', 'major', 'home_library'
        ];
        foreach ($attribsToCheck as $attribute) {
            if (isset($shib->$attribute)) {
                $value = $request->getServer()->get($shib->$attribute);
                if ($attribute != 'cat_password') {
                    $user->$attribute = $value;
                } else {
                    $catPassword = $value;
                }
            }
        }

        if (!empty($user->cat_username)) {
            $user->saveCredentials(
                $user->cat_username,
                null
            );
        }

        // Save and return the user object:
        $user->save();
        return $user;
    }

    /**
     * Get the URL to establish a session
     * @param string $target Full URL where external authentication method should
     * send user after login (some drivers may override this).
     *
     * @return bool|string
     */
    public function getSessionInitiator($target)
    {
        $config = $this->getConfig();
        if (isset($config->Shibboleth->target)) {
            $shibTarget = $config->Shibboleth->target;
        } else {
            $shibTarget = $target;
        }
        $append = (strpos($shibTarget, '?') !== false) ? '&' : '?';
        $sessionInitiator = $config->Shibboleth->login
            . '?target=' . urlencode($shibTarget)
            . urlencode($append . 'auth_method=Shibboleth');

        if (isset($config->Shibboleth->provider_id)) {
            $sessionInitiator = $sessionInitiator . '&entityID=' .
                urlencode($config->Shibboleth->provider_id);
        }

        return $sessionInitiator;
    }

    /**
     * Has the user's login expired?
     *
     * @return bool
     */
    public function isExpired()
    {
        $config = $this->getConfig();
        if (isset($config->Shibboleth->username)
            && isset($config->Shibboleth->logout)
        ) {
            $username = isset($_SERVER[$config->Shibboleth->username])
                ? $_SERVER[$config->Shibboleth->username] : null;
            return empty($username);
        }
        return false;
    }

    /**
     * Perform cleanup at logout time.
     *
     * @param string $url URL to redirect user to after logging out.
     *
     * @return string     Redirect URL
     */
    public function logout($url)
    {
        $config = $this->getConfig();
        if (isset($config->Shibboleth->logout)
            && !empty($config->Shibboleth->logout)
        ) {
            $url = $config->Shibboleth->logout . '?return=' . urlencode($url);
        }

        return $url;
    }

    /**
     * Extract required user attributes from the configuration.
     *
     * @return array      Only username and attribute-related values
     */
    protected function getRequiredAttributes()
    {
        // Special case -- store username as-is to establish return array:
        $sortedUserAttributes = [];

        $shib = $this->getConfig()->Shibboleth;
        foreach ($shib as $key => $value) {
            if (preg_match("/userattribute_[0-9]{1,}/", $key)) {
                $valueKey = 'userattribute_value_' . substr($key, 14);
                $sortedUserAttributes[$value] = isset($shib->$valueKey)
                    ? $shib->$valueKey : null;

                if (empty($sortedUserAttributes[$value])) {
                    throw new AuthException(
                        "User attribute value of " . $value . " is missing!"
                    );
                }
            }
        }

        return $sortedUserAttributes;
    }
}
