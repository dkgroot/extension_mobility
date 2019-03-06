<?php

/* Declare a namespace, available from PHP 5.3 forward. */

namespace cisco;

class service
{
    public $class_error = array();
    public $dev_login = false;
    public $conf_ini = array();
    public $conf_db = array();
    public $xml_url = '';
    public $host_url = '';
    public $conf_path;
    public $page_title;
    public $page_text;
    public $view_action;
    public $view_type = 'cxml';
    public $user_agent;
    public $req_data = array();
    public $sessionid;
    public $fields = array();
    private $form_path = '';

    const LOG_FATAL = 0;
    const LOG_ERROR = 1;
    const LOG_WARN = 2;
    const LOG_INFO = 3;
    const LOG_DEBUG = 4;
    const LOG_TRACE = 5;
    private $logLevel = self::LOG_ERROR;

    public function __construct() {
        $this->conf_path = __DIR__;
        if (file_exists(__DIR__ . '/cisco_service.ini')) {
            $this->conf_ini = parse_ini_file(__DIR__ . "/cisco_service.ini", true);
            $this->init_path();
        }
        $host_param = parse_url($_SERVER["REQUEST_URI"]);
        $this->host_url = "http" . (($_SERVER['SERVER_PORT'] == 443) ? "s" : "") . "://" . $_SERVER['HTTP_HOST'] . $host_param['path'];
        $request = $_REQUEST;
        $this->xml_url = $this->host_url . '?' . $this->array_key2str($request, '=', '&amp;');

        $driverNamespace = "\\cisco\service";
        if (class_exists($driverNamespace, false)) {
            foreach (glob(__DIR__ . "/lib/*.class.php") as $driver) {
                if (preg_match("/\/([a-z1-9]*)\.class\.php$/i", $driver, $matches)) {
                    $name = $matches[1];
                    $class = $driverNamespace . "\\" . $name;
                    if (!class_exists($class, false)) {
                        include($driver);
                    }
                    if (class_exists($class, false)) {
                        $this->$name = new $class($this);
                    } else {
                        throw new \Exception("Invalid Class inside in the include folder: " . print_r($class));
                    }
                }
            }
        } else {
            throw new \Exception("Incorrect namespace:" . print_r($driverNamespace));
            return;
        }
        $this->form_path = __DIR__ . '/views/default/';
        if (!empty($request['locale'])) {
            $loc_code = $this->extconfigs->getextConfig('locale2code', $request['locale']);
            if (!empty($loc_code)) {
                if (file_exists(__DIR__ . '/views/' . $loc_code . '/service.xml.php')) {
                    $this->form_path = __DIR__ . '/views/' . $loc_code . '/';
                }
            }
        }

        if (!empty($this->conf_ini['ami'])) {
            if ($this->ami->connect($this->conf_ini['ami']['HOST'] . ':' . $this->conf_ini['ami']['PORT'],
                                    $this->conf_ini['ami']['USER'], $this->conf_ini['ami']['PASS'], 'off') === false) {
                  throw new \RuntimeException('Could not connect to Asterisk Management Interface.');
            }
        }

        $this->user_agent = $_SERVER['HTTP_USER_AGENT'];
        if ($this->user_agent == "html") {
            $this->view_type = "html";
        } else {
            $this->view_type = "cxml";
        }
    }
    
    public function __destruct() {
        if ($this->ami) {
            $this->ami->disconnect();
        }
    }

    private function init_path() {
        if (!empty($this->conf_ini['general'])) {
            if (!empty($this->conf_ini['general']['page_title'])) {
                $this->page_title = $this->conf_ini['general']['page_title'];
            }
            if (!empty($this->conf_ini['general']['dbalias'])) {
                $db_section = $this->conf_ini['general']['dbalias'];
                $this->conf_db = $this->conf_ini[$db_section];
            }
        }
    }

    private function request2map(&$map = Array())
    {
        $request = $_REQUEST;
        foreach ($map as $key => $value) {
            $requestid = $value['request'];
            if (!empty($request[$requestid])) {
                $map[$key] = $request[$requestid];
            } else {
                if (is_array($map[$key]) && isset($map[$key]['default'])) {
                    $map[$key] = $map[$key]['default'];
                } else {
                    $map[$key] = '';
                    $this->view_action = 'error';
                    $this->page_text = 'invalid param :' . $value;
                    return array('error' => $this->page_text);
                }
            }
        }
        return $map;
    }

    private function log($message, $level = self::LOG_INFO)
    {
        if ($level <= $this->logLevel) {
            error_log(date('r').' - '.$message);
        }
    }

    public function process_request () {
        $request = $_REQUEST;
        //$session = isset($_SESSION) ? $_SESSION : '';
        $sessionid = isset($_COOKIE['sessionid']) ? $_COOKIE['sessionid'] : '';
        //$msg = '';
        $resp = array();
        if (empty($request['action'])) {
            return $resp;
        }
        $cmd_id = $request['action'];
        $this->view_action = $cmd_id;
        switch ($cmd_id) {
            case 'loginform':
                $required = Array(
                    'name' => Array('request' => 'name', 'default' => '#DEVICENAME#'),
                );
                $this->view_action = 'login';
                $this->request2map($required);

                if (isset($request['sessionid'])) {
                    // if session is already provided (by push from chan_sccp) there is no need to request a new one
                    $this->sessionid = $request['sessionid'];
                } else {
                    $resp = $this->request_session($required);
                    if ($resp['result'] === true) {
                        $this->sessionid = $resp['ami']['SessionID'];
                        $this->fields = $resp['ami'];
                    }
                }
                if (isset($this->sessionid)) {
                    $url = Array('name' => $required['name'], 'action' => 'login', 'sessionid' => $this->sessionid);
                    $this->xml_url = $this->host_url . '?' . $this->array_key2str($url, '=', '&amp;');
                } else {
                    $this->view_action = 'error';
                    $this->page_text = 'Session could not be Requested';
                }
                break;
            case 'login':
                $required = array(
                    'name' => Array('request' => 'name', 'default' => '#DEVICENAME#'),
                    'sessionid' => Array('request' => 'sessionid'),
                    'userid' => Array('request' => 'userid'),
                    'pincode' => Array('request' => 'pincode'),
                );
                $this->request2map($required);
                $resp = $this->validate_login($required);
                if ($resp['result'] !== false) {
                    $this->view_action = 'info';
                    $this->page_text =  'Login Successfull (Timeout:' . $resp['ami']['TimeOut'] . ')';
                } else {
                    $this->view_action = 'error';
                    $this->page_text = $resp['error_msg'] . ":" . $resp['ami'];
                }
                break;
            case 'logout':
                $required = array(
                    'name' => Array('request' => 'name', 'default' => '#DEVICENAME#'),
                    'sessionid' => Array('request' => 'sessionid'),
                );
                $this->request2map($required);
                //$required['sessionid'] = $session;
                $resp = $this->User_logout($required);
                $this->view_action = 'info';
                $this->page_text = 'logged out';
                break;
            default:
                break;
        }
        return $resp;
    }

    public function ServiceShowPage() {
        $this->dev_login = false;
        /*
        if (!empty($request['username'])) {
            if (!empty($this->device_login(array('username' => $request['username'])))) {
                $this->dev_login = true;
            }
        }
        */
        switch ($this->view_action) {
            case 'login':
                setcookie ('sessionid', $this->sessionid, $expires = time()+1800, $path = "/");
                header('Expires: '.gmdate('D, d M Y H:i:s \G\M\T', time() + (60 * 5)));		/* 5 min timeout */
                $this->pagedata = array(
                    "general" => array(
                        "name" => _("Login"),
                        "page" => implode(".", array($this->form_path . 'login', $this->view_type, 'php'))
                    ),
                );
                break;
                
            case 'logout':
                $this->pagedata = array(
                    "general" => array(
                        "name" => _("Logout"),
                        "page" => implode(".", array($this->form_path . 'info', $this->view_type, 'php'))
                    ),
                );
                break;

            case 'error':
                $this->pagedata = array(
                    "general" => array(
                        "name" => _("Info"),
                        "page" => implode(".", array($this->form_path . 'info', $this->view_type, 'php'))
                    ),
                );
                break;

            case 'info':
                $this->pagedata = array(
                    "general" => array(
                        "name" => _("Info"),
                        "page" => implode(".", array($this->form_path . 'info', $this->view_type, 'php'))
                    ),
                );
                break;

            default:
                $this->pagedata = array(
                    "general" => array(
                        "name" => _("General"),
                        //"t" => $this->dbinterface->info(),
                        "page" => implode(".", array($this->form_path . 'service', $this->view_type, 'php'))
                    ),
                );
                break;
        }

        if (!empty($this->pagedata)) {
            foreach ($this->pagedata as &$page) {
                ob_start();
                if (file_exists($page['page'])) {
                    include($page['page']);
                    $page['content'] = ob_get_contents();
                } else {
                    $page['content'] = 'file not found : ' . $page['page'];
                }
                ob_end_clean();
            }
        }
        return $this->pagedata;
    }

    /*
     *   Login User 
     */
    private function User_login($param = array())
    {
        $result = false;
        if (isset($param['pincode']) && isset($param['userid'])) {
            $result = true;
        }
        return $result;
    }

    /*
     *   Logout User 
     */
    private function User_logout($param = array())
    {
        $result = false;
        if (isset($param['devicename'])) {
            $result = true;
        }
        return $result;
    }

    private function request_session($param = array()) {
        $result = false;
        $actionid = rand();
        $ami_result = $this->ami->sendRequest('SCCPUserRequestSession', 
            [
                'ActionID' => $actionid, 
                'DeviceID' => $param['name']
            ]
        );
        /* result set
        "Message: SCCPUserRequestSession\r\n"
        "DeviceID: %s\r\n"
        "prevUserID: %s\r\n" // not yet implemented
        "SessionID: %lx\r\n"
        "Status: %d\r\n"
        "StatusText: %s\r\n"
        "TimeOut: %ld\r\n"
        "\r\n",
        */
        if (isset($ami_result['Response']) && $ami_result['Response'] === 'Success' &&
            isset($ami_result['ActionID']) && $ami_result['ActionID'] == $actionid)
        {
            $result = true;
        } else {
            // throw ?
        }
        return array('result' => $result, 'ami' => $ami_result);
    }
    /*
     * Check User and Permission
     * 
     */
    private function validate_login($param = array()) {
        $actionid = rand();
        $ami_result = $this->ami->sendRequest('SCCPUserProgressLogin', 
            [
                'ActionID' => $actionid, 
                'DeviceID' => $param['name'],
                'SessionID' => $param['sessionid'],
                'UserID' => $param['userid'],
                'Pincode' => $param['pincode'], 
            ]
        );
        /* result set
        "Message: SCCPUserProgressLogin\r\n"
        "DeviceID: %s\r\n"
        "UserID: %s\r\n"
        "SessionID: %lx\r\n"
        "Status: %d\r\n"
        "StatusText: %s\r\n"
        "TimeOut: %ld\r\n"
        "\r\n",
        */
        if (
            isset($ami_result['Response']) && $ami_result['Response'] === 'Success' && 
            isset($ami_result['ActionID']) && $ami_result['ActionID'] == $actionid
        ) {
            return array('result' => true, 'ami' => $ami_result);
        }
        return array('result' => false, 'error_msg' => 'failed to login you in.', 'ami' => $ami_result);
    }

    public function array_key2str($data = Array(), $keydelimer = '=', $rowdelimer = ';') {
        $res = '';
        foreach ($data as $key => $value) {
            if (!empty($res)) {
                $res .= $rowdelimer;
            }
            $res .= $key . $keydelimer . $value;
        }
        return $res;
    }

}
