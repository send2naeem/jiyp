<?php

class UserMatch extends BaseModel
{

    protected $tableName = 'user_matches';
    protected $relationMap = array(
        'user_id' => '',
        'match_id' => '',
        'confirmed' => '',
        'requested_on' => '',
        'matched_on' => '',
    );

    public function __construct($db = null)
    {
        parent::__construct($db);
    }

    public function getMatchCount($data, $returnValueOnly = false)
    {
        $userID = getParam('user_id', 0, $data);
        $sql = "SELECT COUNT(DISTINCT match_id) as total_matches FROM " . $this->getTableName() . " um
                WHERE
                    um.user_id = :user_id
                    AND confirmed = 1
                    AND um.match_id IN(
                        SELECT user_id FROM " . $this->getTableName() . "
                        WHERE match_id = :user_id AND confirmed = 1
                    )
                ";
        $res = $this->findBySql($sql, array(':user_id' => $userID));
        if ($returnValueOnly)
            return $res['total_matches'];

        return array('success' => true, 'data' => array('totalMatches' => $res['total_matches']));
    }

    public function getMutualMatchCount($data, $returnValueOnly = false)
    {
        $firstUserID = getParam('user_id', 0, $data);
        $secondUserID = getParam('other_id', 0, $data);

        $sql = "SELECT COUNT(DISTINCT um.match_id) as total_matches
                FROM " . $this->getTableName() . " um
                WHERE
                    um.match_id IN (
                        SELECT match_id
                        FROM " . $this->getTableName() . "
                        WHERE user_id = :user_a_id AND confirmed = 1
                    )
                    AND um.match_id IN (
                        SELECT user_id
                        FROM " . $this->getTableName() . "
                        WHERE match_id = :user_a_id AND confirmed = 1
                    )
                    AND um.user_id = :user_b_id
                    AND um.confirmed = 1
                ";

        $res = $this->findBySql($sql, array(':user_a_id' => $firstUserID, ':user_b_id' => $secondUserID));
        if ($returnValueOnly)
            return $res['total_matches'];

        return array('success' => true, 'data' => array('totalMutualMatches' => $res['total_matches']));
    }

    /*
     * Like or Dislike someone
     */

    public function likeOrDislikeThem($data)
    {
        $likeOrDislike = getParam('like', 0, $data);
        $likeOrDislike = $likeOrDislike == 0 ? 0 : 1;
        $userID = getParam('user_id', 0, $data);
        $theirID = getParam('other_id', 0, $data);
        if ($match = $this->findAllByAttributes(array('user_id' => array($userID, $theirID), 'match_id' => array($userID, $theirID))))
        {
            $isMatch = count($match) == 2;
            if (!$isMatch)
            {
                $likeRequest = current($match);
                $requestedBy = $likeRequest['user_id'];
                if ($requestedBy == $theirID)
                {
                    $data['action'] = 'confirm';
                    $matchAdded = $this->addMatch($data);
                    if ($matchAdded)
                        return array('success' => true, 'code' => RESPONSE_CODE_SUCCESS);
                    else
                        return array('success' => false, 'code' => RESPONSE_CODE_DB_EXCEPTION);
                }
                else
                {
                    $likeStatus = $likeRequest['confirmed'];
                    $likeStatus = abs($likeStatus - 1); // invert status i.e set to 0 if is 1 and vice versa
                    if (parent::update(array('confirmed' => $likeStatus), array('user_id' => $userID, 'match_id' => $theirID)))
                        return array('success' => true, 'code' => RESPONSE_CODE_SUCCESS);
                    else
                        return array('success' => false, 'code' => RESPONSE_CODE_DB_EXCEPTION);
                }
            }
            else
            {
                $request = current($match);
                $confirm = next($match);
                if ($request['user_id'] == $userID)
                    $likeStatus = $request['confirmed'];
                else
                    $likeStatus = $confirm['confirmed'];

                $likeStatus = abs($likeStatus - 1); // invert status i.e set to 0 if is 1 and vice versa
                if (parent::update(array('confirmed' => $likeStatus), array('user_id' => $userID, 'match_id' => $theirID)))
                    return array('success' => true, 'code' => RESPONSE_CODE_SUCCESS);
                else
                    return array('success' => false, 'code' => RESPONSE_CODE_EXCEPTION);
            }
        }
        else
        {
            $data['action'] = 'request';
            $matchAdded = $this->addMatch($data);
            if ($matchAdded)
                return array('success' => true, 'code' => RESPONSE_CODE_SUCCESS);
            else
                return array('success' => false, 'code' => RESPONSE_CODE_DB_EXCEPTION);
        }
    }

    private function addMatch($data)
    {
        $action = getParam('action', 'request', $data);
        $userID = getParam('user_id', 0, $data);
        $matchID = getParam('other_id', 0, $data);
        
        $values = array(
            'user_id' => $userID,
            'match_id' => $matchID,
            'confirmed' => 1
        );
        
        if ($action == 'request')
            $values['requested_on'] = date('Y-m-d H:i:s');
        else
            $values['matched_on'] = date('Y-m-d H:i:s');

        return parent::add($values);
    }

    public function getMatches($data, $returnValueOnly = false)
    {
        $limit = getParam('page_size', NULL, $data);
        $page = getParam('page', 1, $data);
        $page = $page < 1 ? 1 : $page;

        $sql = "SELECT 
                    u.user_id, u.first_name, u.last_name, u.email, u.cover_photo, u.profile_photo,
                    us.tagline, us.mode, us.passengers, us.location_lat, us.location_long,
                    6371 * 2 * ASIN(SQRT(
                    POWER(SIN((us.location_lat - ABS(:user_lat)) * PI()/180 / 2),
                    2) + COS(us.location_lat * PI()/180 ) * COS(ABS(:user_lat) *
                    PI()/180) * POWER(SIN((us.location_long - :user_long) *
                    PI()/180 / 2), 2) )) as distance            
                FROM " . $this->getTableName() . " um
                INNER JOIN " . User::model()->getTableName() . " u
                    ON u.user_id = um.match_id
                INNER JOIN " . UserStatus::model()->getTableName() . " us
                    ON us.user_id = um.match_id
                WHERE
                    um.user_id = :user_id
                    AND confirmed = 1
                    AND um.match_id IN(
                        SELECT user_id FROM " . $this->getTableName() . "
                        WHERE match_id = :user_id AND confirmed = 1
                    )
                ORDER BY distance ASC
                ";
        if ($limit !== NULL)
        {
            $sql .= " LIMIT " . $limit . " OFFSET " . $limit * ($page - 1);
        }
        $params = array(
            ':user_lat' => getParam('location_lat', '0.0', $data),
            ':user_long' => getParam('location_long', '0.0', $data),
            ':user_id' => getParam('user_id', 0, $data),
        );
        $matches = $this->findAllBySql($sql, $params);

        if ($returnValueOnly)
            return $matches;

        return array('success' => true, 'code' => RESPONSE_CODE_SUCCESS, 'data' => array('matches' => $matches));
    }

    public function getRequests($data)
    {
        $limit = getParam('page_size', NULL, $data);
        $page = getParam('page', 1, $data);
        $page = $page < 1 ? 1 : $page;

        $sql = "SELECT 
                    u.user_id, u.first_name, u.last_name, u.email, u.cover_photo, u.profile_photo,
                    us.tagline, us.mode, us.passengers, us.location_lat, us.location_long,
                    6371 * 2 * ASIN(SQRT(
                    POWER(SIN((us.location_lat - ABS(:user_lat)) * PI()/180 / 2),
                    2) + COS(us.location_lat * PI()/180 ) * COS(ABS(:user_lat) *
                    PI()/180) * POWER(SIN((us.location_long - :user_long) *
                    PI()/180 / 2), 2) )) as distance
                FROM " . $this->getTableName() . " um
                INNER JOIN " . User::model()->getTableName() . " u
                    ON u.user_id = um.user_id
                INNER JOIN " . UserStatus::model()->getTableName() . " us
                    ON us.user_id = um.user_id
                WHERE
                    um.match_id = :user_id
                    AND confirmed = 1
                    AND um.user_id NOT IN(
                        SELECT match_id FROM " . $this->getTableName() . "
                        WHERE user_id = :user_id AND confirmed IN (0, 1)
                    )
                ";
        if ($limit !== NULL)
        {
            $sql .= " LIMIT " . $limit . " OFFSET " . $limit * ($page - 1);
        }

        $params = array(
            ':user_lat' => getParam('location_lat', '0.0', $data),
            ':user_long' => getParam('location_long', '0.0', $data),
            ':user_id' => getParam('user_id', 0, $data),
        );
        $requests = $this->findAllBySql($sql, $params);

        return array('success' => true, 'code' => RESPONSE_CODE_SUCCESS, 'data' => array('requests' => $requests));
    }

}

?>
