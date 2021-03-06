<?php

defined('RESPONSE_CODE_SUCCESS') or define('RESPONSE_CODE_SUCCESS', '1');
defined('RESPONSE_CODE_DB_RECORD_EXISTS') or define('RESPONSE_CODE_DB_RECORD_EXISTS', '0');
defined('RESPONSE_CODE_ERROR_DATA_INVALID') or define('RESPONSE_CODE_ERROR_DATA_INVALID', '-1');
defined('RESPONSE_CODE_DB_EXCEPTION') or define('RESPONSE_CODE_DB_EXCEPTION', '-2');
defined('RESPONSE_CODE_EXCEPTION') or define('RESPONSE_CODE_EXCEPTION', '-3');
defined('RESPONSE_CODE_DB_RECORD_NOT_EXISTS') or define('RESPONSE_CODE_DB_RECORD_NOT_EXISTS', '-4');
defined('RESPONSE_CODE_FB_INVALID_TOKEN') or define('RESPONSE_CODE_FB_INVALID_TOKEN', '-5');

define('FACEBOOK_SDK_V4_SRC_DIR', getcwd() . '/include/facebook-php-sdk/Facebook/');
defined('FACEBOOK_APP_ID_LOCAL') or define('FACEBOOK_APP_ID_LOCAL', '1426289784327875');
defined('FACEBOOK_APP_SECRET_LOCAL') or define('FACEBOOK_APP_SECRET_LOCAL', 'aeb293cf492d8da09159c0882dc0ad30');

defined('FACEBOOK_APP_ID') or define('FACEBOOK_APP_ID', FACEBOOK_APP_ID_LOCAL);
defined('FACEBOOK_APP_SECRET') or define('FACEBOOK_APP_SECRET', FACEBOOK_APP_SECRET_LOCAL);

defined('BASE_URL') or define('BASE_URL', trim($baseUrl, '/'));
defined('DS') or define('DS', DIRECTORY_SEPARATOR);
defined('UPLOAD_DIRECTORY') or define('UPLOAD_DIRECTORY', getcwd() . DS . 'uploads');
defined('UPLOAD_DIRECTORY_URL') or define('UPLOAD_DIRECTORY_URL', BASE_URL . '/' . 'uploads');
?>