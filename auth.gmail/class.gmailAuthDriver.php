<?php
/*
 * Copyright 2014 Prateek Sureka - Sureka Group <surekap (at) gmail.com>
 * This file is a custom designed plugin for use with Pydio
 *
 * This plugin is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This plugin is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://github.com/surekap/pydio-auth-gmail/>.
 *
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Standard auth implementation, stores the data in serialized files
 * @package AjaXplorer_Plugins
 * @subpackage Auth
 */
class gmailAuthDriver extends AbstractAuthDriver
{
    public $usersSerFile;
    public $driverName = "gmail";

    public function init($options)
    {
        parent::init($options);
        $this->usersSerFile = AJXP_VarsFilter::filter($this->getOption("USERS_FILEPATH"));
    }

    public function performChecks()
    {
        if(!isset($this->options)) return;
        if (isset($this->options["FAST_CHECKS"]) && $this->options["FAST_CHECKS"] === true) {
            return;
        }
        $usersDir = dirname($this->usersSerFile);
        if (!is_dir($usersDir) || !is_writable($usersDir)) {
            throw new Exception("Parent folder for users file is either inexistent or not writeable.");
        }
        if (is_file($this->usersSerFile) && !is_writable($this->usersSerFile)) {
            throw new Exception("Users file exists but is not writeable!");
        }
    }

    protected function _listAllUsers()
    {
        $users = AJXP_Utils::loadSerialFile($this->usersSerFile);
        if (AuthService::ignoreUserCase()) {
            $users = array_combine(array_map("strtolower", array_keys($users)), array_values($users));
        }
        ConfService::getConfStorageImpl()->filterUsersByGroup($users, "/", true);
        return $users;
    }

    public function listUsers($baseGroup = "/")
    {
        $adminUser = $this->options["AJXP_ADMIN_LOGIN"];
        if (isSet($this->options["ADMIN_USER"])) {
            $adminUser = $this->options["AJXP_ADMIN_LOGIN"];
        }
        return array($adminUser => $adminUser);
    }

    public function supportsUsersPagination()
    {
        return false;
    }
    
    public function userExists($login)
    {
        return true;
    }

    public function checkPassword($login, $pass, $seed)
    {
        $adminUser = $this->options["AJXP_ADMIN_LOGIN"];
	file_put_contents("/tmp/gm.log", $adminUser."\n", FILE_APPEND | LOCK_EX);
	file_put_contents("/tmp/gm.log", $this->options["ADMIN_USER"]."\n", FILE_APPEND | LOCK_EX);
        $result = false;
        
	if (!$adminUser) $adminUser = "root";
        if ($login != $adminUser){
            $result = $this->authenticate($login, $pass);

            if ($result){
                // Create the serialized entry if it doesn't exist
                $users = $this->_listAllUsers();
                if (!is_array($users)) $users = array();
                if (!array_key_exists($login, $users)){
                    $users[$login] = "dummypass";
                }
                AJXP_Utils::saveSerialFile($this->usersSerFile, $users);
            }
        }else{
            if(AuthService::ignoreUserCase()) $login = strtolower($login);
            $users = $this->_listAllUsers();
            $userStoredPass = $users[$login];
            if(!$userStoredPass) return false;
            if ($seed == "-1") { // Seed = -1 means that password is not encoded.
                return AJXP_Utils::pbkdf2_validate_password($pass, $userStoredPass);//($userStoredPass == md5($pass));
            } else {
                return (md5($userStoredPass.$seed) == $pass);
            }
        }
        

        return $result;
        
    }

    public function usersEditable()
    {
        return false;
    }
    public function passwordsEditable()
    {
        return false;
    }

    public function createUser($login, $passwd)
    {
    }
    public function changePassword($login, $newPass)
    {
    }
    public function deleteUser($login)
    {
    } 
    public function getUserPass($login)
    {
        return "";
    }
    
    /**
     * This method should handle any authentication and report back to the subject
     *
     * @param   array   $credentials  Array holding the user credentials
     *
     * @return  boolean
     *
     * @since   1.5
     */
    public function authenticate($username, $password)
    {
        file_put_contents("/tmp/gm.log", "authenticating\n", FILE_APPEND | LOCK_EX);
        $success = 0;
        $credentials = array(
                        "username" => $username,
                        "password" => $password
                        );

        // Check if we have curl or not
        if (function_exists('curl_init'))
        {
            file_put_contents("/tmp/gm.log", "curl-passed\n", FILE_APPEND | LOCK_EX);
            // Check if we have a username and password
            if (strlen($credentials['username']) && strlen($credentials['password']))
            {
                $offset = strpos($credentials['username'], '@');
                file_put_contents("/tmp/gm.log", "calling curl_init\n", FILE_APPEND | LOCK_EX);
                $curl = curl_init('https://mail.google.com/mail/feed/atom');
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 1);
                //curl_setopt($curl, CURLOPT_HEADER, 1);
                curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
                curl_setopt($curl, CURLOPT_USERPWD, $credentials['username'] . ':' . $credentials['password']);
                file_put_contents("/tmp/gm.log", "calling curl-exec\n", FILE_APPEND | LOCK_EX);
                curl_exec($curl);
                $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                file_put_contents("/tmp/gm.log", "code:".$code."\n", FILE_APPEND | LOCK_EX);
                switch ($code)
                {
                    case 200:
                        $message = "Access Granted";
                        $success = 1;
                        break;

                    case 401:
                        $message = "Access Denied";
                        break;

                    default:
                        $message = "Unknown Access Denied";
                        break;
                }
            }
            else
            {
                $message = "User Blacklisted";
            }
        }
        else
        {
            $message = 'curl isn\'t insalled';
        }
        file_put_contents("/tmp/gm.log", "message:".$message.",success:".$success."\n", FILE_APPEND | LOCK_EX);
        if ($success){
            return true;
        }
        else
        {
            return false;
        }
    }

}


