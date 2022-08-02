<?php

/**
 * Discourse API client for PHP
 *
 * This is the Discourse API client for PHP
 * This is a very experimental API implementation.
 *
 * @category  DiscourseAPI
 * @package   DiscourseAPI
 * @author    Original author DiscourseHosting <richard@discoursehosting.com>
 * @copyright 2013, DiscourseHosting.com
 * @license   http://www.gnu.org/licenses/gpl-2.0.html GPLv2
 * @link      https://github.com/discoursehosting/discourse-api-php
 */

class DiscourseAPI
{
    private $_protocol = 'http';
    private $_apiKey = null;
    private $_dcHostname = null;
    private $_httpAuthName = '';
    private $_httpAuthPass = '';
    private $_ignoreSSL = false;

    function __construct($dcHostname, $apiKey = null, $protocol = 'http', $httpAuthName = '', $httpAuthPass = '', $ignoreSSL = false)
    {
        $this->_dcHostname = $dcHostname;
        $this->_apiKey = $apiKey;
        $this->_protocol = $protocol;
        $this->_httpAuthName = $httpAuthName;
        $this->_httpAuthPass = $httpAuthPass;
        $this->_ignoreSSL = $ignoreSSL;
    }

    private function _getRequest($reqString, $paramArray = null, $apiUser = 'system')
    {
        if ($paramArray == null) {
            $paramArray = array();
        }
        $ch = curl_init();
        $url = sprintf(
            '%s://%s%s?%s',
            $this->_protocol,
            $this->_dcHostname,
            $reqString,
            http_build_query($paramArray)
        );

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Api-Key: " . $this->_apiKey,
            "Api-Username: $apiUser"
        ]);

        if (!empty($this->_httpAuthName) && !empty($this->_httpAuthPass)) {
            curl_setopt($ch, CURLOPT_USERPWD, $this->_httpAuthName . ":" . $this->_httpAuthPass);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        }

        if ($this->_ignoreSSL)
        {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $body = curl_exec($ch);

        $curl_errno = curl_errno($ch);
        $curl_error = curl_error($ch);

        if ($curl_errno > 0) {
            throw new Exception("cURL Error ($curl_errno): $curl_error", 1);
        }

        $rc = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $resObj = new \stdClass();
        $resObj->http_code = $rc;
        $resObj->apiresult = json_decode($body);
        return $resObj;
    }

    private function _putRequest($reqString, $paramArray, $apiUser = 'system')
    {
        return $this->_putpostRequest($reqString, $paramArray, $apiUser, true);
    }

    private function _postRequest($reqString, $paramArray, $apiUser = 'system')
    {

        return $this->_putpostRequest($reqString, $paramArray, $apiUser, false);
    }

    private function _putpostRequest($reqString, $paramArray, $apiUser = 'system', $putMethod = false)
    {
        $ch = curl_init();
        $url = sprintf(
            '%s://%s%s',
            $this->_protocol,
            $this->_dcHostname,
            $reqString);

        curl_setopt($ch, CURLOPT_URL, $url);

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Api-Key: " . $this->_apiKey,
            "Api-Username: $apiUser"
        ]);

        // Hack !
        $params = http_build_query($paramArray);
        $params = preg_replace('@tags%5B[0-9]+%5D@', 'tags%5B%5D', $params);

        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if ($putMethod) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        }

        if (!empty($this->_httpAuthName) && !empty($this->_httpAuthPass)) {
            curl_setopt($ch, CURLOPT_USERPWD, $this->_httpAuthName . ":" . $this->_httpAuthPass);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        }

        if ($this->_ignoreSSL)
        {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        }

        $body = curl_exec($ch);

        $curl_errno = curl_errno($ch);
        $curl_error = curl_error($ch);

        if ($curl_errno > 0) {
            throw new Exception("cURL Error ($curl_errno): $curl_error", 1);
        }

        $rc = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $resObj = new \stdClass();
        $resObj->http_code = $rc;
        $resObj->apiresult = json_decode($body);

        usleep(500000); // wait a 1/2 of a sec

        return $resObj;
    }

    /**
     * group
     *
     * @param string $groupname         name of group
     * @param string $usernames     users to add to group
     *
     * @return mixed HTTP return code and API return object
     */

    function group($groupname, $usernames = array())
    {
        $obj = $this->_getRequest("/admin/groups.json");
        if ($obj->http_code != 200) {
            return false;
        }

        $groupId = false;
        foreach ($obj->apiresult as $group) {
            if ($group->name === $groupname) {
                $groupId = $group->id;
                break;
            }
        }

        $params = array(
            'group' => array(
                'name' => $groupname,
                'usernames' => implode(',', $usernames)
            )
        );

        if ($groupId) {
            return $this->_putRequest('/admin/groups/' . $groupId, $params);
        } else {
            return $this->_postRequest('/admin/groups', $params);
        }
    }

    /**
     * getGroups
     *
     * @return mixed HTTP return code and API return object
     */

    function getGroups()
    {
        return $this->_getRequest("/admin/groups.json");
    }

    /**
     * getGroupMembers
     *
     * @param string $group         name of group
     * @return mixed HTTP return code and API return object
     */

    function getGroupMembers($group)
    {
        return $this->_getRequest("/groups/{$group}/members.json");
    }


    /**
     * getPostsByEmbeddedURL
     *
     * @param string $url         url to lookup
     * @return mixed HTTP return code and API return object
     */

    function getPostsByEmbeddedURL($url)
    {
        return $this->_getRequest("/embed/info", array('embed_url' => $url));
    }

    function getPostByExternalID($external_id)
    {
        $apiUser = 'system';

        $reqString = "/t/external_id/{$external_id}.json";

        $ch = curl_init();
        $url = sprintf(
            '%s://%s%s',
            $this->_protocol,
            $this->_dcHostname,
            $reqString
        );

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "API-KEY: " . $this->_apiKey,
            "API-USERNAME: $apiUser"
        ]);

        if (!empty($this->_httpAuthName) && !empty($this->_httpAuthPass)) {
            curl_setopt($ch, CURLOPT_USERPWD, $this->_httpAuthName . ":" . $this->_httpAuthPass);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        }

        if ($this->_ignoreSSL)
        {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        }

        curl_setopt($ch, CURLOPT_URL, $url);

        // include the response headers in the output
        curl_setopt($ch, CURLOPT_HEADER, true);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($ch);
        $rc = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $curl_errno = curl_errno($ch);
        $curl_error = curl_error($ch);

        if ($curl_errno > 0) {
            throw new Exception("cURL Error ($curl_errno): $curl_error", 1);
        }

        // how big are the headers
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headerStr = substr($result, 0, $headerSize);
        $body = substr($result, $headerSize);

        // convert headers to array
        $headers = $this->headersToArray( $headerStr );

        curl_close($ch);

        if (!empty($headers['location']))
            return $headers['location'];

        return '';
    }

    private function headersToArray( $str )
    {
        $headers = array();
        $headersTmpArray = explode( "\r\n" , $str );
        for ( $i = 0 ; $i < count( $headersTmpArray ) ; ++$i )
        {
            // we dont care about the two \r\n lines at the end of the headers
            if ( strlen( $headersTmpArray[$i] ) > 0 )
            {
                // the headers start with HTTP status codes, which do not contain a colon so we can filter them out too
                if ( strpos( $headersTmpArray[$i] , ":" ) )
                {
                    $headerName = substr( $headersTmpArray[$i] , 0 , strpos( $headersTmpArray[$i] , ":" ) );
                    $headerValue = substr( $headersTmpArray[$i] , strpos( $headersTmpArray[$i] , ":" )+1 );
                    $headers[trim($headerName)] = trim($headerValue);
                }
            }
        }
        return $headers;
    }

    /**
     * createUser
     *
     * @param string $name         name of new user
     * @param string $userName     username of new user
     * @param string $emailAddress email address of new user
     * @param string $password     password of new user
     *
     * @return mixed HTTP return code and API return object
     */

    function createUser($name, $userName, $emailAddress, $password, $active = false)
    {
        $obj = $this->_getRequest('/users/hp.json');
        if ($obj->http_code != 200) {
            return false;
        }

        $params = array(
            'name' => $name,
            'username' => $userName,
            'email' => $emailAddress,
            'password' => $password,
            'challenge' => strrev($obj->apiresult->challenge),
            'password_confirmation' => $obj->apiresult->value,
            'active' => $active
        );

        return $this->_postRequest('/users.json', $params);
    }

    /**
     * createUser
     *
     * @param string $name         name of new user
     * @param string $userName     username of new user
     * @param string $emailAddress email address of new user
     * @param string $password     password of new user
     *
     * @return mixed HTTP return code and API return object
     */

    function createUserNoPassword($name, $userName, $emailAddress, $active = false)
    {
        $params = array(
            'name' => $name,
            'username' => $userName,
            'email' => $emailAddress,
            'password' => uniqid().uniqid(),
            'active' => $active
        );

        return $this->_postRequest('/users.json', $params);
    }

    /**
     * activateUser
     *
     * @param integer $userId      id of user to activate
     *
     * @return mixed HTTP return code
     */

    function activateUser($userId)
    {
        return $this->_putRequest("/admin/users/{$userId}/activate", array());
    }

    /**
     * suspendUser
     *
     * @param integer $userId      id of user to suspend
     *
     * @return mixed HTTP return code
     */

    function suspendUser($userId)
    {
        return $this->_putRequest("/admin/users/{$userId}/suspend", array());
    }

    /**
     * getUsernameByEmail
     *
     * @param string $email     email of user
     *
     * @return mixed HTTP return code and API return object
     */

    function getUsernameByEmail($email)
    {
        if (empty($email))
            throw new Exception("Empty email used for getting a username", 1);

        $users = $this->_getRequest(
            '/admin/users/list/active.json',
            ['filter' => $email, 'show_emails' => 'true']
        );

        foreach ($users->apiresult as $user) {
            if ($user->email === $email) {
                return $user->username;
            }
        }

        return false;
    }

    /**
     * getUserByUsername
     *
     * @param string $userName     username of user
     *
     * @return mixed HTTP return code and API return object
     */

    function getUserByUsername($userName)
    {
        return $this->_getRequest("/users/{$userName}.json");
    }

    /**
     * getUserByExternalID
     *
     * @param string $externalID     external id of sso user
     *
     * @return mixed HTTP return code and API return object
     */
    function getUserByExternalID($externalID)
    {
        return $this->_getRequest("/users/by-external/{$externalID}.json");
    }

    /**
     * createCategory
     *
     * @param string $categoryName name of new category
     * @param string $color        color code of new category (six hex chars, no #)
     * @param string $textColor    optional color code of text for new category
     * @param string $userName     optional user to create category as
     *
     * @return mixed HTTP return code and API return object
     */

    function createCategory($categoryName, $color, $textColor = '000000', $userName = 'system')
    {
        $params = array(
            'name' => $categoryName,
            'color' => $color,
            'text_color' => $textColor
        );
        return $this->_postRequest('/categories', $params, $userName);
    }

    /**
     * getTopic
     *
     * @param string $topicId   id of topic
     * @param string $userName  User name in order to get info on the watch status on the topic
     *
     * @return mixed HTTP return code and API return object
     */
    function getTopic($topicID, $userName = 'system')
    {
        return $this->_getRequest('/t/' . $topicID . '.json', array(), $userName);
    }

    /**
     * Tell if a given username is watching a given topic
     *
     * @param string $topicId   id of topic
     * @param string $userName  User name in order to get info on the watch status on the topic
     *
     * @return mixed The level of watch (0, 1, 2 or 3)
     *
     * Muted: 0
     * Normal: 1
     * Tracking: 2
     * Watching: 3
     */
    function isWatchingTopic($topicId, $userName)
    {
        $r = $this->getTopic($topicId, $userName);

        return $r->apiresult->details->notification_level;
    }

    function getTopicIdForPost($postId)
    {
        $r = $this->_getRequest('/posts/' . $postId . '.json');

        if (!empty($r->apiresult) || !empty($r->apiresult->topic_id))
            return $r->apiresult->topic_id;

        return false;
    }

    /**
     * createTopic
     *
     * @param string $topicTitle   title of topic
     * @param string $bodyText     body text of topic post
     * @param string $categoryName category to create topic in
     * @param string $userName     user to create topic as
     * @param string $replyToId    post id to reply as (deprecated, use createPost for that)
     *
     * @return mixed HTTP return code and API return object
     */
    function createTopic($topicTitle, $bodyText, $categoryId, $userName, $replyToId = 0, $created_at = null, $tags = array(), $external_id = null)
    {
        $params = array(
            'title' => $topicTitle,
            'raw' => $bodyText,
            'category' => $categoryId,
            'archetype' => 'regular',
            'created_at' => $created_at
        );

        if (!empty($external_id))
            $params['external_id'] = $external_id;

        foreach ($tags as $k => $tag)
            $params["tags[$k]"] = $tag;

        return $this->_postRequest('/posts', $params, $userName);
    }

    function createTopicForEmbed(String $topicTitle, String $bodyText, Int $categoryId, String $userName, String $embedURL = '', Int $external_id = null)
    {
        $params = array(
            'title' => $topicTitle,
            'raw' => $bodyText,
            'archetype' => 'regular'
        );

        if (!empty($categoryId))
            $params['category'] = $categoryId;
        if (!empty($embedURL))
            $params['embed_url'] = $embedURL;
        if (!empty($external_id))
            $params['external_id'] = $external_id;

        return $this->_postRequest('/posts', $params, $userName);
    }

    /**
     * Same as createTopicForEmbed but returns the topic ID for the newly created topic. If the topic already exists
     * returns the topic ID as well.
     */
    function createTopicForEmbed2(String $topicTitle, String $bodyText, Int $categoryId, String $userName, String $embedURL = '', Int $external_id = null)
    {
		// create a topic
        $r = $this->createTopicForEmbed(
			$topicTitle,
			$bodyText,
			$categoryId,
			$userName,
			$embedURL,
			$external_id
		);

		if (empty($r->apiresult) || !isset($r->apiresult->topic_id))
		{
			if ($r->http_code == 422)
			{
				// The topic may already exist for this page...

				// Find the topic id for the given URL
				$r2 = $this->getPostsByEmbeddedURL($embedURL);

				if (isset($r2->apiresult->topic_id))
					return $r2->apiresult->topic_id;

                throw new Exception("Error Processing Request - $topicTitle \n"
                                    . print_r($r, true) . "\n"
                                    . print_r($r2, true), 1);
			}
		}

        $this->rebakePost($r->apiresult->id);

		return $r->apiresult->topic_id;
    }

    /**
     * Rebake the html for a post
     */
    function rebakePost($postId)
    {
        return $this->_putRequest("/posts/{$postId}/rebake", null);
    }

    /**
     * watchTopic
     *
     * watch Topic. If username is given, API-Key must be
     * general API key. Otherwise it will fail.
     * If no username is given, topic will be watched with
     * the system API username
     */
    function watchTopic($topicId, $userName = 'system')
    {
        $params = array(
            'notification_level' => '3'
        );
        return $this->_postRequest("/t/{$topicId}/notifications.json", $params, $userName);
    }

    /**
     * watchTopic
     *
     * watch Topic. If username is given, API-Key must be
     * general API key. Otherwise it will fail.
     * If no username is given, topic will be watched with
     * the system API username
     */
    function unwatchTopic($topicId, $userName = 'system')
    {
        $params = array(
            'notification_level' => '1'
        );
        return $this->_postRequest("/t/{$topicId}/notifications.json", $params, $userName);
    }

    /**
     * createPost
     *
     * @param string $bodyText     body text of topic post
     * @param string $topicId      topic id - must me a string not array
     * @param string $userName     user to create topic as
     *
     * @return mixed HTTP return code and API return object
     */
    function createPost($bodyText, $topicId, $userName, $created_at = null, $postNumber = null)
    {
        $params = array(
            'raw' => $bodyText,
            'topic_id' => $topicId
        );

        if (!empty($created_at))
            $params['created_at'] = $created_at;

        if (!empty($postNumber))
        {
            $params['reply_to_post_number'] = $postNumber;
            $params['nested_post'] = 1;
        }

        return $this->_postRequest('/posts', $params, $userName);
    }

    function inviteUser($email, $topicId, $userName = 'system')
    {
        $params = array(
            'email' => $email,
            'topic_id' => $topicId
        );
        return $this->_postRequest('/t/' . intval($topicId) . '/invite.json', $params, $userName);
    }

    function changeSiteSetting($siteSetting, $value)
    {
        $params = array($siteSetting => $value);
        return $this->_putRequest('/admin/site_settings/' . $siteSetting, $params);
    }

    function getIDByEmail($email)
    {
        $username = $this->getUsernameByEmail($email);
        if ($username) {
            return $this->_getRequest('/users/' . $username . '/activity.json')->apiresult->user->id;
        } else {
            return false;
        }
    }

    function logoutByEmail($email)
    {
        $user_id = $this->getIDByEmail($email);
        $params  = array('username_or_email' => $email);
        return $this->_postRequest('/admin/users/' . $user_id . '/log_out', $params);
    }

    function getUserinfoByName($username)
    {
        return $this->_getRequest("/users/{$username}.json");
    }

    function acceptPost($topicId, $userName)
    {
        $params = array(
            'id' => $topicId
        );
        return $this->_postRequest('/solution/accept.json', $params, $userName);
    }

    /**
     * Create a user on discourse
     * Returns the newly created username
     */
	function createDiscourseUser(User $user, int $increment = 0)
	{
        $username = str_replace(' ', '.', $user->getRealName());

        if (!empty($increment))
            $username .= $increment;

        $userEmail = $user->getEmail();
        $userEmail = str_replace('tripleperformance.fr', 'neayi.com', $userEmail);

		$r = $this->createUserNoPassword($user->getRealName(), $username, $userEmail, true);

		if (empty($r->apiresult))
			throw new \Exception("Could not connect to the Discourse API", 1);

		if ($r->apiresult->success)
			return $username;

		if (!empty($r->apiresult->errors->username[0]) &&
			strpos($r->apiresult->errors->username[0], 'unique') !== false)
		{
			$increment++;
			return $this->createDiscourseUser($user, $increment);
		}

		throw new \Exception($r->apiresult->message);
	}
}
