<?php

class Invite extends BaseModel
{

    protected $tableName = 'invites';
    protected $relationMap = array(
        'id' => '',
        'invitee_id' => '',
        'invited_user_email' => '',
        'email_sent' => '',
        'accepted' => '',
        'invited_on' => '',
        'accepted_on' => '',
    );

    public function __construct($db = null)
    {
        parent::__construct($db);
    }

    public function sendInvite($data)
    {
        $userID = getParam('user_id', 0, $data);
        $theirEmail = getParam('email', '', $data);
        $theirEmail = strtolower($theirEmail);
        if (empty($theirEmail))
            return array('success' => false, 'code' => RESPONSE_CODE_ERROR_DATA_INVALID, 'message' => 'Invalid data provided');

        if ($this->findByAttributes(array('invitee_id' => $userID, 'invited_user_email' => $theirEmail)))
            return array('success' => false, 'code' => RESPONSE_CODE_DB_RECORD_EXISTS, 'message' => 'An invitation has already been sent to this email address by you');

        $emailSent = 0;
        if (getParam('send_email', 0, $data) == 1)
        {
            if (false)//send email
                $emailSent = 1;
        }        
        
        $values = array(
            'invitee_id' => $userID,
            'invited_user_email' => $theirEmail,
            'email_sent' => $emailSent,
            'accepted' => 0,
            'invited_on' => date('Y-m-d H:i:s')
        );

        $inviteID = $this->add($values);
        
        if ($inviteID)
            return array('success' => true, 'code' => RESPONSE_CODE_SUCCESS, 'message' => 'Invitation sent');
        else
            return array('success' => false, 'code' => RESPONSE_CODE_DB_EXCEPTION, 'message' => 'Could not send invitaion due to some technical issues');
    }

}

?>
