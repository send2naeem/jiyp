<?php

$routeMap = array(
    'user' => array(
        'model' => 'User',
        'actions' => array(
            'join' => array(
                'callable' => 'signup',
                'authenticate' => false,
            ),
            'login' => array(
                'callable' => 'login',
                'authenticate' => false,
            ),
            'logout' => array(
                'callable' => 'logout',
            ),
            'profile-compact' => array(
                'callable' => 'getCompactProfile',
            ),
            'profile' => array(
                'callable' => 'getProfile',
            ),
            'others-profile' => array(
                'callable' => 'getOtherUserProfile',
            ),
            'update-profile' => array(
                'callable' => 'updateProfile',
            ),
            'change-profile-picture' => array(
                'callable' => 'changeProfilePicture',
            ),
            'change-cover-photo' => array(
                'callable' => 'changeCoverPhoto',
            ),
            'delete' => array(
                'callable' => 'delete',
                'authenticate' => false,
            ),
        ),
    ),
    'user-status' => array(
        'model' => 'UserStatus',
        'actions' => array(
            'nearby' => array(
                'callable' => 'getNearByUsers',
            ),
            'update' => array(
                'callable' => 'update',
            ),
        ),
    ),
    'user-match' => array(
        'model' => 'UserMatch',
        'actions' => array(
            'match-count' => array(
                'callable' => 'getMatchCount',
            ),
            'mutual-match-count' => array(
                'callable' => 'getMutualMatchCount',
            ),
            'like' => array(
                'callable' => 'likeOrDislikeThem',
            ),
            'get-matches' => array(
                'callable' => 'getMatches',
            ),
            'get-requests' => array(
                'callable' => 'getRequests',
            ),
        ),
    ),
    'user-photo' => array(
        'model' => 'UserPhoto',
        'actions' => array(
            'upload' => array(
                'callable' => 'upload',
            ),
        ),
    ),
    'invite' => array(
        'model' => 'Invite',
        'actions' => array(
            'send-invite' => array(
                'callable' => 'sendInvite',
            ),
        ),
    ),
);
?>