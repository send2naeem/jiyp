<?php

require_once getcwd() . '/include/globals.inc.php';
require_once getcwd() . '/include/route.map.inc.php';
require_once getcwd() . '/include/facebook-php-sdk/autoload.php';
includeClasses();

function includeClasses()
{
    foreach (glob(getcwd() . "/models/*.php") as $filename)
    {
        include_once $filename;
    }
}

function processRequest()
{
    global $routeMap;
    $url = empty($_SERVER['REDIRECT_URL']) ? '/default' : $_SERVER['REDIRECT_URL'];
    $url = explode('/', $url);
    $thisRoute = strtolower($url[1]);
    $thisAction = empty($url[2]) ? 'index' : strtolower($url[2]);
    if (!key_exists($thisRoute, $routeMap))
    {
        renderJSON(array('success' => false, 'message' => 'Requested page not found.'));
    }
    else
    {
        $thisRoute = $routeMap[$thisRoute];
        if (!key_exists($thisAction, $thisRoute['actions']))
        {
            renderJSON(array('success' => false, 'message' => 'Requested page not found.'));
        }
        else
        {
            $thisAction = $thisRoute['actions'][$thisAction];
            $authenticated = true;
            $requestData = array_merge($_POST, $_GET, $_FILES);
            $db = getDB();
            if (!isset($thisAction['authenticate']) || $thisAction['authenticate'] != false)
            {
                $authenticated = User::model()->authenticate($requestData);
            }
            if (!$authenticated)
            {
                renderJSON(array('success' => false, 'message' => 'Could not authenticate the token.'));
            }

            $model = new $thisRoute['model']($db);
            $callable = $thisAction['callable'];
            $response = $model->$callable($requestData);

            renderJSON($response);
        }
    }
}

function getParam($name, $default = null, $data, $trim = true)
{
    $name = explode('.', $name);
    foreach ($name as $index)
    {
        if (!isset($data[$index]))
            return $default;
        $data = $data[$index];
    }

    if ($trim && is_string($data))
        return trim($data);

    return $data;
}

function getFileUrl($type, $fileName)
{
    $directory = "";
    switch (strtolower($type))
    {
        case 'js' : $directory = "assets/js";
            break;
        case 'css' : $directory = "assets/css";
            break;
        case 'image' : $directory = "assets/img";
            break;
        case 'image_xma' : $directory = "assets/images_XMA";
            break;
        case 'document' : $directory = "documents";
            break;
        default : return false;
    }

    return BASE_URL . $directory . '/' . $fileName;
}

function getFilePath($type, $fileName)
{
    $directory = "";
    switch (strtolower($type))
    {
        case 'js' : $directory = "assets/js";
            break;
        case 'css' : $directory = "assets/css";
            break;
        case 'image' : $directory = "assets/img";
            break;
        case 'image_xma' : $directory = "assets/images_XMA";
            break;
        case 'document' : $directory = "documents";
            break;
        default : return false;
    }

    return getcwd() . '/' . $directory . '/' . $fileName;
}

function sendMail($from, $to, $subject, $body, $attachments = array())
{
    require_once getcwd() . '/include/classes/PHPMailerAutoload.php';
    $mail = new PHPMailer;

    $mail->IsSMTP();
    $mail->SMTPDebug = 0;
//    $mail->Debugoutput = 'html';
    $mail->SMTPAuth = true;
    $mail->SMTPSecure = 'tls';
    $mail->Host = "mail.uk2.net";
    $mail->Port = 587;
    $mail->Username = "info@accendomarketsrecruitment.com";
    $mail->Password = "accendo2014";

    if (!empty($from))
    {
        if (is_string($from))
            $mail->From = $from;
        else
        {
            $mail->From = $from['email'];
            if (isset($from['name']))
                $mail->FromName = $from['name'];
        }
    }
    if (is_string($to))
        $mail->AddAddress($to);
    else
    {
        foreach ($to as $recipient)
        {
            if (is_string($recipient))
                $mail->AddAddress($recipient);
            else
                $mail->AddAddress($recipient['email'], isset($recipient['name']) ? $recipient['name'] : '' );
        }
    }
    $mail->Subject = $subject;
    $mail->WordWrap = 70; // set word wrap
    $mail->MsgHTML($body);
    foreach ($attachments as $filePath)
    {
        $mail->AddAttachment($filePath);
    }
    if ($mail->send())
        return true;

    $fh = fopen('mail.log', 'a');
    fwrite($fh, date('d-m-Y H:i:s') . "\n");
    fwrite($fh, $mail->ErrorInfo . "\n\n");
    fclose($fh);
    return false;
}

function getDB()
{
    global $dbHost, $dbName, $dbUser, $dbPassword;
    $dbh = new PDO("mysql:host={$dbHost};dbname={$dbName}", $dbUser, $dbPassword);
    return $dbh;
}

function shortenFileName($name, $limit = 100)
{
    if (strlen($name) < $limit)
        return $name;

    $nameArr = explode('.', $name);
    $extension = end($nameArr);
    $name = substr($name, 0, $limit - strlen($extension) - 1);
    return $name . '.' . $extension;
}

function makePath($path)
{
    $dir = pathinfo($path, PATHINFO_DIRNAME);
    if (is_dir($dir))
    {
        return true;
    }
    else
    {
        if (makePath($dir))
        {
            if (mkdir($dir))
            {
                chmod($dir, 0777);
                return true;
            }
        }
    }
    return false;
}

function printR($obj)
{
    print('<pre>');
    print_r($obj);
    print('</pre>');
}

function array_to_obj($array, &$obj)
{
    foreach ($array as $key => $value)
    {
        if (is_array($value))
        {
            $obj->$key = new stdClass();
            array_to_obj($value, $obj->$key);
        }
        else
        {
            $obj->$key = $value;
        }
    }
    return $obj;
}

function arrayToObject($array)
{
    $object = new stdClass();
    return array_to_obj($array, $object);
}

function renderJSON($data)
{
    header('Content-Type: application/json');
    echo json_encode($data);
    exit(0);
}

?>