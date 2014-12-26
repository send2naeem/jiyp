<?php

class LoginHistory extends BaseModel
{

    protected $tableName = 'login_history';
    protected $relationMap = array(
        'user_id' => '',
        'auth_token' => '',
        'logged_in_on' => '',
    );

    public function __construct($db = null)
    {
        parent::__construct($db);
    }

    public function write($data)
    {
        $authToken = getParam('auth_token', '', $data);
        $userID = getParam('user_id', '', $data);
        $now = date('Y-m-d H:i:s');
        return parent::add(array('user_id' => $userID, 'auth_token' => $authToken, 'logged_in_on' => $now));
    }

}

?>
