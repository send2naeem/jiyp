<?php

class UserPhoto extends BaseModel
{

    protected $tableName = 'user_photos';
    protected $relationMap = array(
        'id' => '',
        'user_id' => '',
        'file_name' => '',
        'photo_url' => '',
        'added_on' => '',
        'trashed' => '',
    );

    public function __construct($db = null)
    {
        parent::__construct($db);
    }

    public function upload($data)
    {
        $photo = getParam('photo', '', $data);
        $photoID = getParam('photo_id', '', $data);
        $userID = getParam('user_id', 0, $data);
        $fileErrors = array();
        $fileValidators = array(
            'allowedTypes' => array('image/jpeg', 'image/png'),
            'allowedExtensions' => array('jpg', 'jpeg', 'png')
        );
        if ($this->isValidFile($photo, $fileValidators, $fileErrors))
        {
            $filters = array(
                'rename' => uniqid() . $photo['name'],
                'limitFileName' => 100,
                'uploadDirectory' => UPLOAD_DIRECTORY . DS . $userID,
            );
            if ($fileName = $this->saveUploadedFile($photo, $filters))
            {
                $photoUrl = UPLOAD_DIRECTORY_URL . '/' . $userID . '/' . $fileName;

                if ($existingPhoto = $this->findByAttributes(array('user_id' => $userID, 'id' => $photoID)))
                {
                    if (parent::update(array('file_name' => $fileName, 'photo_url' => $photoUrl), array('id' => $existingPhoto['id'])))
                    {
                        $oldFilePath = UPLOAD_DIRECTORY . DS . $userID . DS . $existingPhoto['file_name'];
                        if (is_file($oldFilePath))
                            unlink($oldFilePath);
                        return array('success' => true, 'code' => RESPONSE_CODE_SUCCESS, 'data' => array('photo' => array('photo_url' => $photoUrl, 'photo_id' => $photoID)));
                    }
                    else
                        return array('success' => false, 'code' => RESPONSE_CODE_DB_EXCEPTION);
                }
                else
                {
                    $values = array(
                        'user_id' => $userID,
                        'file_name' => $fileName,
                        'photo_url' => $photoUrl,
                        'added_on' => date('Y-m-d H:i:s'),
                        'trashed' => 0
                    );

                    if ($photoID = parent::add($values))
                        return array('success' => true, 'code' => RESPONSE_CODE_SUCCESS, 'data' => array('photo' => array('photo_url' => $photoUrl, 'photo_id' => $photoID)));
                    else
                        return array('success' => false, 'code' => RESPONSE_CODE_DB_EXCEPTION);
                }

                return array('success' => true, 'code' => RESPONSE_CODE_SUCCESS, 'data' => array('photo_url' => $photoUrl));
            }
        }

        return array('success' => false, 'code' => RESPONSE_CODE_ERROR_DATA_INVALID, 'messages' => $fileErrors);
    }

}

?>
