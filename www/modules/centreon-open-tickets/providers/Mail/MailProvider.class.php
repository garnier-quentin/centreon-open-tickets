<?php
/*
 * Copyright 2016 Centreon (http://www.centreon.com/)
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

class MailProvider extends AbstractProvider {
    
    protected function _setDefaultValueMain() {
        parent::_setDefaultValueMain(1);
    }
     
    protected function _setDefaultValueExtra() {
        $this->default_data['from'] = '{$user.email}';
        $this->default_data['subject'] = 'Issue {$ticket_id} - {include file="file:$centreon_open_tickets_path/providers/Abstract/templates/display_title.ihtml"}';
        
        $this->default_data['clones']['headerMail'] = array(
            array('Name' => 'MIME-Version', 'Value' => '1.0'),
            array('Name' => 'Content-Type', 'Value' => 'text/html; charset=utf8')
        );
    }
    
    /**
     * Check form
     *
     * @return a string
     */
    protected function _checkConfigForm() {
        $this->_check_error_message = '';
        $this->_check_error_message_append = '';
        
        $this->_checkFormValue('from', "Please set 'From' value");
        $this->_checkFormValue('to', "Please set 'To' value");
        $this->_checkFormValue('subject', "Please set 'Subject' value");
        $this->_checkFormValue('macro_ticket_id', "Please set 'Macro Ticket ID' value");
        $this->_checkFormInteger('confirm_autoclose', "'Confirm popup autoclose' must be a number");
        
        $this->_checkLists();
        
        if ($this->_check_error_message != '') {
            throw new Exception($this->_check_error_message);
        }
    }
    
    /**
     * Build the specifc config: from, to, subject, body, headers
     *
     * @return void
     */
    protected function _getConfigContainer1Extra() {
        $tpl = new Smarty();
        $tpl = initSmartyTplForPopup($this->_centreon_open_tickets_path, $tpl, 'providers/Mail/templates', $this->_centreon_path);
        
        $tpl->assign("centreon_open_tickets_path", $this->_centreon_open_tickets_path);
        $tpl->assign("img_brick", "./modules/centreon-open-tickets/images/brick.png");
        $tpl->assign("header", array("mail" => _("Mail")));
        
        // Form
        $from_html = '<input size="50" name="from" type="text" value="' . $this->_getFormValue('from') . '" />';
        $to_html = '<input size="50" name="to" type="text" value="' . $this->_getFormValue('to') . '" />';
        $subject_html = '<input size="50" name="subject" type="text" value="' . htmlentities($this->_getFormValue('subject')) . '" />';

        $array_form = array(
            'from' => array('label' => _("From") . $this->_required_field, 'html' => $from_html),
            'to' => array('label' => _("To") . $this->_required_field, 'html' => $to_html),
            'subject' => array('label' => _("Subject") . $this->_required_field, 'html' => $subject_html),
            'header' => array('label' => _("Headers")),
        );
        
        // Clone part
        $headerMailName_html = '<input id="headerMailName_#index#" size="20" name="headerMailName[#index#]" type="text" />';
        $headerMailValue_html = '<input id="headerMailValue_#index#" size="20" name="headerMailValue[#index#]" type="text" />';
        $array_form['headerMail'] = array(
            array('label' => _("Name"), 'html' => $headerMailName_html),
            array('label' => _("Value"), 'html' => $headerMailValue_html),
        );
        
        $tpl->assign('form', $array_form);
        
        $this->_config['container1_html'] .= $tpl->fetch('conf_container1extra.ihtml');
        
        $this->_config['clones']['headerMail'] = $this->_getCloneValue('headerMail');
    }
    
    /**
     * Build the specific advanced config: -
     *
     * @return void
     */
    protected function _getConfigContainer2Extra() {
        
    }
    
    protected function saveConfigExtra() {
        $this->_save_config['clones']['headerMail'] = $this->_getCloneSubmitted('headerMail', array('Name', 'Value'));
        $this->_save_config['simple']['from'] = $this->_submitted_config['from'];
        $this->_save_config['simple']['to'] = $this->_submitted_config['to'];
        $this->_save_config['simple']['subject'] = $this->_submitted_config['subject'];
    }
    
    public function validateFormatPopup() {
        $result = array('code' => 0, 'message' => 'ok');
        
        $this->validateFormatPopupLists($result);
        return $result;
    }
    
    protected function doSubmit($db_storage, $contact, $host_problems, $service_problems) {
        $result = array('ticket_id' => null, 'ticket_error_message' => null,
                        'ticket_is_ok' => 0, 'ticket_time' => time());

        try {
            $query = "INSERT INTO mod_open_tickets
  (`timestamp`, `user`) VALUES ('" . $result['ticket_time'] . "', '" . $db_storage->escape($contact['name']) . "')";            
            $db_storage->query($query);
            $result['ticket_id'] = $db_storage->lastinsertId('mod_open_tickets');
        } catch (Exception $e) {
            $result['ticket_error_message'] = $e->getMessage();
            return $result;
        }
        
        $tpl = new Smarty();
        $tpl = initSmartyTplForPopup($this->_centreon_open_tickets_path, $tpl, 'providers/Abstract/templates', $this->_centreon_path);
        
        $tpl->assign("centreon_open_tickets_path", $this->_centreon_open_tickets_path);
        $tpl->assign('user', $contact);
        $tpl->assign('host_selected', $host_problems);
        $tpl->assign('service_selected', $service_problems);
        $tpl->assign('ticket_id', $result['ticket_id']);
        $this->assignSubmittedValues($tpl);        
        
        // We send the mail
        $tpl->assign('string', $this->rule_data['from']);
        $from = $tpl->fetch('eval.ihtml');
        $headers = "From: " . $from;
        if (isset($this->rule_data['clones']['headerMail'])) {
            foreach ($this->rule_data['clones']['headerMail'] as $values) {
                $headers .= "\r\n" . $values['Name'] . ':' . $values['Value'];
            }
        }

        $tpl->assign('string', $this->rule_data['subject']);
        $subject = $tpl->fetch('eval.ihtml');
        mail($this->rule_data['to'], $subject, $this->body, $headers);
        
        $this->saveHistory($db_storage, $result, 
            array('no_create_ticket_id' => true, 'contact' => $contact, 'host_problems' => $host_problems, 'service_problems' => $service_problems,
                  'subject' => $subject, 
                  'data_type' => self::DATA_TYPE_JSON, 'data' => json_encode(array('body' => $this->body, 'from' => $from, 'headers' => $headers, 'to' => $this->rule_data['to'])))
        );
        
        return $result;
    }
}
