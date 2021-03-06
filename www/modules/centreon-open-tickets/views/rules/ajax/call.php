<?php
/*
 * Copyright 2015 Centreon (http://www.centreon.com/)
 *
 * Centreon is a full-fledged industry-strength solution that meets 
 * the needs in IT infrastructure and application monitoring for 
 * service performance.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0  
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,*
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

require_once dirname(__FILE__) . '/../../../centreon-open-tickets.conf.php';
require_once $centreon_path . 'www/modules/centreon-open-tickets/class/centreonDBManager.class.php';
require_once $centreon_path . 'www/modules/centreon-open-tickets/class/rule.php';
require_once $centreon_path . 'www/modules/centreon-open-tickets/providers/register.php';
require_once $centreon_path . "www/class/centreonXMLBGRequest.class.php";
$centreon_open_tickets_path = $centreon_path . "www/modules/centreon-open-tickets/";
require_once $centreon_open_tickets_path . 'providers/Abstract/AbstractProvider.class.php';

session_start();
$centreon_bg = new CentreonXMLBGRequest(session_id(), 1, 1, 0, 1);
$db = new centreonDBManager();
$rule = new Centreon_OpenTickets_Rule($db);

if (isset($_SESSION['centreon'])) {
    $centreon = $_SESSION['centreon'];
} else {
    exit;
}

define('SMARTY_DIR', "$centreon_path/GPL_LIB/Smarty/libs/");
require_once SMARTY_DIR . "Smarty.class.php";
require_once $centreon_path . 'www/include/common/common-Func.php';

$resultat = array("code" => 0, "msg" => "");
$actions = array("get-form-config" => dirname(__FILE__) . "/actions/getFormConfig.php",
                 "save-form-config" => dirname(__FILE__) . "/actions/saveFormConfig.php",
                 "validate-format-popup" => dirname(__FILE__) . "/actions/validateFormatPopup.php",
                 "submit-ticket" => dirname(__FILE__) . "/actions/submitTicket.php",
                 "close-ticket" => dirname(__FILE__) . "/actions/closeTicket.php",
                 "service-ack" => dirname(__FILE__) . "/actions/serviceAck.php",);
if (!isset($_POST['data'])) {
    $resultat = array("code" => 1, "msg" => "POST 'data' needed.");
} else {
    $get_information = json_decode($_POST['data'], true);
    if (!isset($get_information['action']) ||
        !isset($actions[$get_information['action']])) {
        $resultat = array("code" => 1, "msg" => "Action not good.");
    } else {
        include($actions[$get_information['action']]);
    }
}

header("Content-type: text/plain");
echo json_encode($resultat);

?>
