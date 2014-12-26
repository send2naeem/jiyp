<?php

class UserStatus extends BaseModel
{

    protected $tableName = 'user_status';
    protected $relationMap = array(
        'user_id' => '',
        'tagline' => '',
        'location_lat' => '',
        'location_long' => '',
        'mode' => '',
        'passengers' => '',
        'updated_on' => '',
    );

    public function __construct($db = null)
    {
        parent::__construct($db);
    }

    /*
     * returs all users with in $radius kilometers from user location
     */

    public function getNearByUsers($data)
    {
        $userID = getParam('user_id', 0, $data);
        $userStatus = $this->findByAttributes(array('user_id' => $userID));
        if (!empty($userStatus))
        {
            $radius = getParam('radius', NULL, $data);
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
                    FROM " . User::model()->getTableName() . " u
                    INNER JOIN " . $this->getTableName() . " us
                    ON u.user_id = us.user_id
                    HAVING
                        u.user_id != :user_id
                        AND NOT(TRIM(us.tagline) = '' OR tagline IS NULL)
                        AND u.user_id NOT IN(
                            SELECT um.user_id FROM " . UserMatch::model()->getTableName() . " um
                            WHERE
                                um.match_id = :user_id
                                AND confirmed = 1
                                AND um.user_id IN(
                                    SELECT match_id FROM " . UserMatch::model()->getTableName() . "
                                    WHERE
                                        user_id = :user_id
                                        AND confirmed = 1
                                )
                        )
                        " . ($radius !== NULL ? " AND distance <= :radius " : "") . "
                    ORDER BY distance ASC";

            if ($limit !== NULL)
            {
                $sql .= " LIMIT " . $limit . " OFFSET " . $limit * ($page - 1);
            }

            $params = array(
                ':user_lat' => getParam('location_lat', '0.0', $data),
                ':user_long' => getParam('location_long', '0.0', $data),
                ':user_id' => $userID,
            );
            if ($radius !== NULL)
                $params[':radius'] = $radius;
            $nearByUsers = $this->findAllBySql($sql, $params);

            return array('success' => true, 'code' => RESPONSE_CODE_SUCCESS, 'data' => array('nearByUsers' => $nearByUsers));
        }

        return array('success' => false, 'code' => RESPONSE_CODE_DB_RECORD_NOT_EXISTS);
    }

    public function add($data)
    {
        $userID = getParam('user_id', 0, $data);
        $values = array(
            'user_id' => $userID,
            'tagline' => getParam('tagline', '', $data),
            'location_lat' => getParam('location_lat', '0.0', $data),
            'location_long' => getParam('location_long', '0.0', $data),
            'mode' => getParam('mode', '', $data),
            'passengers' => getParam('passengers', 0, $data),
            'updated_on' => date('Y-m-d H:i:s')
        );

        return parent::add($values);
    }

    public function update($data, $null = null)
    {
        $fieldsToBeUpdated = array();
        $fieldsCanBeUpdated = array(
            'tagline',
            'location_lat',
            'location_long',
            'mode',
            'passengers',
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

}

?>
