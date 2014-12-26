<?php

use Facebook\FacebookSession;
use Facebook\FacebookRequest;
use Facebook\GraphUser;
use Facebook\FacebookRequestException;

//FacebookSession::setDefaultApplication(FACEBOOK_APP_ID, FACEBOOK_APP_SECRET);

class User extends BaseModel
{

    protected $tableName = 'users';
    protected $relationMap = array(
        'user_id' => '',
        'first_name' => '',
        'last_name' => '',
        'email' => '',
        'phone' => '',
        'fb_id' => '',
        'auth_token' => '',
        'joined_on' => '',
        'cover_photo' => '',
        'profile_photo' => '',
        'updated_on' => '',
    );

    public function __construct($db = null)
    {
        parent::__construct($db);
    }

    public function authenticate($data)
    {
        $token = getParam('auth_token', '', $data);
        $userID = getParam('user_id', '', $data);
        return (bool) $this->findByAttributes(array('auth_token' => $token, 'user_id' => $userID), array('user_id'));
    }

    public function signup($data)
    {
        try
        {
            if (!$this->validate($data))
            {
                return array('success' => false, 'code' => RESPONSE_CODE_ERROR_DATA_INVALID, 'message' => 'Invalid data provided.');
            }

            $fbID = getParam('fb_id', '', $data);
            $alreadyRegistered = $this->findByAttributes(array('fb_id' => $fbID));

            if ($alreadyRegistered)
                return array('success' => false, 'code' => RESPONSE_CODE_DB_RECORD_EXISTS, 'message' => 'This user is already registered.');
            
            $values = array(
                'first_name' => getParam('first_name', '', $data),
                'last_name' => getParam('last_name', '', $data),
                'email' => getParam('email', '', $data),
                'phone' => getParam('phone', '', $data),
                'fb_id' => getParam('fb_id', '', $data),
                'auth_token' => getParam('auth_token', '', $data),
                'joined_on' => date('Y-m-d H:i:s'),
                'cover_photo' => getParam('cover_photo', '', $data),
                'profile_photo' => getParam('profile_photo', '', $data),
            );
            $userID = parent::add($values);
            if ($userID)
            {
                UserStatus::model()->add(array_merge(array('user_id' => $userID), $data));
                return $this->login($data);
            }
        }
        catch (PDOException $e)
        {
            return array('success' => false, 'code' => RESPONSE_CODE_DB_EXCEPTION, 'message' => $e->getMessage());
        }
    }

    private function validate($data)
    {
        $fbID = getParam('fb_id', '', $data);
        $authToken = getParam('auth_token', '', $data);
        return (!empty($fbID) && !empty($authToken));
    }

    public function login($data)
    {
        $fbID = getParam('fb_id', '', $data);
        $authToken = getParam('auth_token', '', $data);
        $fbVerfied = $this->verifyFacebookToken($data);
        if (is_object($fbVerfied))
            return array('success' => false, 'message' => $fbVerfied->getMessage(), 'code' => RESPONSE_CODE_FB_INVALID_TOKEN);
        else if (!$fbVerfied)
            return array('success' => false, 'message' => 'Facebook could not verified access token', 'code' => RESPONSE_CODE_FB_INVALID_TOKEN);

        $user = $this->findByAttributes(array('fb_id' => $fbID), array('user_id', 'first_name', 'last_name', 'email', 'cover_photo', 'profile_photo'));
        if (!empty($user))
        {
            $uniqID = uniqid();
            $authToken = $uniqID . substr_replace($authToken, $uniqID, 0, rand(20, 50));
            $authToken = strtoupper(substr($authToken, 0, 255));
            $user['auth_token'] = $authToken;
            $data['user_id'] = $user['user_id'];
            $status = UserStatus::model()->findByAttributes(array('user_id' => $user['user_id']), array('tagline', 'mode', 'passengers'));
            $this->update(array('auth_token' => $authToken), array('user_id' => $user['user_id']));
            LoginHistory::model()->write($data);
            return array('success' => true, 'code' => RESPONSE_CODE_SUCCESS, 'data' => array('user' => $user, 'status' => $status));
        }
        return array('success' => false, 'code' => RESPONSE_CODE_DB_RECORD_NOT_EXISTS, 'message' => 'Please signup before signing in.');
    }

    public function verifyFacebookToken($data)
    {
        return true;
        FacebookSession::enableAppSecretProof(false);
        $authToken = getParam('auth_token', NULL, $data);
        $fbID = getParam('fb_id', NULL, $data);
        $session = new FacebookSession($authToken);
        if ($session)
        {
            try
            {
//                return $session->validate();
                $fbRequest = new FacebookRequest($session, 'GET', '/me');
                $userProfile = $fbRequest->execute()->getGraphObject(GraphUser::className());
                return $fbID == $userProfile->getID();
            }
            catch (FacebookRequestException $e)
            {
                return $e;
            }
        }
        return false;
    }

    public function logout($data)
    {
        $userID = getParam('user_id', 0, $data);
        if (parent::update(array('auth_token' => ''), array('user_id' => $userID)))
            return array('success' => true, 'code' => RESPONSE_CODE_SUCCESS);
        else
            return array('success' => false, 'code' => RESPONSE_CODE_DB_RECORD_NOT_EXISTS);
    }

    public function getCompactProfile($id)
    {
        $user = $this->findByAttributes(array('user_id' => $id));
        if (!empty($user))
        {
            return array('success' => true, 'code' => RESPONSE_CODE_SUCCESS, 'data' => array('user' => $user));
        }
        return array('success' => false, 'code' => RESPONSE_CODE_DB_RECORD_NOT_EXISTS);
    }

    public function getProfile($data)
    {
        $userID = getParam('user_id', 0, $data);
        $user = $this->findByAttributes(array('user_id' => $userID));
        if (!empty($user))
        {
            $status = UserStatus::model()->findByAttributes(array('user_id' => $user['user_id']));
            $photos = UserPhoto::model()->findAllByAttributes(array('user_id' => $user['user_id'], 'trashed' => 0), array('photo_url', 'id as photo_id'));
            return array('success' => true, 'code' => RESPONSE_CODE_SUCCESS, 'data' => array('user' => $user, 'status' => $status, 'photos' => $photos));
        }
        return array('success' => false, 'code' => RESPONSE_CODE_DB_RECORD_NOT_EXISTS);
    }

    public function getOtherUserProfile($data)
    {
        $otherUserID = getParam('other_id', 0, $data);
        $response = $this->getProfile(array_merge($data, array('user_id' => $otherUserID)), true);
        if ($response['success'])
        {
            $mutualMatchCount = UserMatch::model()->getMutualMatchCount($data, true);
            $matchCount = UserMatch::model()->getMatchCount(array_merge($data, array('user_id' => $otherUserID)), true);
            $otherUser = $response['data']['user'];
            unset($otherUser['auth_token']);
            unset($otherUser['fb_id']);
            return array(
                'success' => true,
                'data' => array(
                    'user' => $otherUser,
                    'photos' => $response['data']['photos'],
                    'mutualMatchCount' => $mutualMatchCount,
                    'matchCount' => $matchCount,
                ),
                'code' => RESPONSE_CODE_SUCCESS,
            );
        }
        return array('success' => false, 'code' => RESPONSE_CODE_DB_RECORD_NOT_EXISTS);
    }

    public function updateProfile($data)
    {
        $fieldsToBeUpdated = array();
        $fieldsCanBeUpdated = array(
            'first_name',
            'last_name',
            'email',
            'phone',
        );
        foreach ($fieldsCanBeUpdated as $fieldName)
        {
            if (isset($data[$fieldName]))
                $fieldsToBeUpdated[$fieldName] = trim($data[$fieldName]);
        }
        if (empty($fieldsToBeUpdated))
            return array('success' => false, 'code' => RESPONSE_CODE_ERROR_DATA_INVALID, 'message' => 'No data provided');
        $fieldsToBeUpdated['updated_on'] = date('Y-m-d H:i:s');
        $userID = getParam('user_id', 0, $data);
        if (parent::update($fieldsToBeUpdated, array('user_id' => $userID)))
        {
            return array('success' => true, 'code' => RESPONSE_CODE_SUCCESS);
        }
        return array('success' => false, 'code' => RESPONSE_CODE_DB_RECORD_NOT_EXISTS);
    }

    public function changeProfilePicture($data)
    {
        return $this->changePhoto($data, 'profile');
    }

    public function changeCoverPhoto($data)
    {
        return $this->changePhoto($data, 'cover');
    }

    private function changePhoto($data, $photoType)
    {
        $field = $photoType == 'cover' ? 'cover_photo' : 'profile_photo';
        $userID = getParam('user_id', NULL, $data);
        $picture = getParam($field, NULL, $data);
        $errors = array();
        $fileValidators = array(
            'allowedTypes' => array('image/jpeg', 'image/png'),
            'allowedExtensions' => array('jpg', 'jpeg', 'png')
        );
        if (parent::isValidFile($picture, $fileValidators, $errors))
        {
            $filters = array(
                'rename' => uniqid() . $picture['name'],
                'limitFileName' => 100,
                'uploadDirectory' => UPLOAD_DIRECTORY . DS . $userID,
            );
            if ($fileName = parent::saveUploadedFile($picture, $filters))
            {
                $photoUrl = UPLOAD_DIRECTORY_URL . '/' . $userID . '/' . $fileName;
                if (parent::update(array($field => $photoUrl), array('user_id' => $userID)))
                    return array('success' => true, 'code' => RESPONSE_CODE_SUCCESS, 'data' => array('photo_url' => $photoUrl));
                else
                    return array('success' => false, 'code' => RESPONSE_CODE_DB_EXCEPTION);
            }
        }
        return array('success' => false, 'code' => RESPONSE_CODE_ERROR_DATA_INVALID, 'messages' => $errors);
    }
    
    public function delete($data = NULL, $softDelete = false)
    {
        $userID = getParam('user_id', 0, $data);
        $userID = explode(',', $userID);
        $params = array('user_id' => $userID);
        UserStatus::model()->delete($params, $softDelete);
        UserPhoto::model()->delete($params, $softDelete);
        Invite::model()->delete(array('invitee_id' => $userID), $softDelete);
        LoginHistory::model()->delete($params, $softDelete);
        UserMatch::model()->delete(array_merge($params), $softDelete);
        UserMatch::model()->delete(array_merge(array('match_id' => $userID)), $softDelete);
        $deleted = parent::delete($params, $softDelete);
        
        return array('deleted' => $deleted . ' user(s)');
    }

}

?>
