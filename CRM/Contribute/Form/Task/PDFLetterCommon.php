<?php

/*
 +--------------------------------------------------------------------+
 | CiviCRM version 3.4                                                |
 +--------------------------------------------------------------------+
 */

require_once 'CRM/Contact/Form/Task/PDFLetterCommon.php';

/**
 * This class provides the common functionality for creating PDF letter for
 * one or a group of contact ids.
 */
class CRM_Contribute_Form_Task_PDFLetterCommon extends CRM_Contact_Form_Task_PDFLetterCommon
{
    
    /**
     * process the form after the input has been submitted and validated
     *
     * @access public
     * @return None
     */
    static function postProcess( &$form ) 
    {
        $formValues = $form->controller->exportValues( $form->getName( ) );
        watchdog('debug', print_r($form->controller, TRUE));

        // process message template
        require_once 'CRM/Core/BAO/MessageTemplates.php';
        if ( CRM_Utils_Array::value( 'saveTemplate', $formValues ) || CRM_Utils_Array::value( 'updateTemplate', $formValues ) ) {
            $messageTemplate = array( 'msg_text'    => NULL,
                                      'msg_html'    => $formValues['html_message'],
                                      'msg_subject' => NULL,
                                      'is_active'   => true );

            $messageTemplate['pdf_format_id'] = 'null';
            if ( CRM_Utils_Array::value( 'bind_format', $formValues ) && $formValues['format_id'] > 0 ) {
                $messageTemplate['pdf_format_id'] = $formValues['format_id'];
            }
            if ( $formValues['saveTemplate'] ) {
                $messageTemplate['msg_title'] = $formValues['saveTemplateName'];
                CRM_Core_BAO_MessageTemplates::add( $messageTemplate );
            }

            if ( $formValues['template'] && $formValues['updateTemplate']  ) {
                $messageTemplate['id'] = $formValues['template'];
                unset($messageTemplate['msg_title']);
                CRM_Core_BAO_MessageTemplates::add( $messageTemplate );
            }
        }
        else if ( $formValues['template'] > 0 ) {
            if ( CRM_Utils_Array::value( 'bind_format', $formValues ) && $formValues['format_id'] > 0 ) {
                $query = "UPDATE civicrm_msg_template SET pdf_format_id = {$formValues['format_id']} WHERE id = {$formValues['template']}";
            } else {
                $query = "UPDATE civicrm_msg_template SET pdf_format_id = NULL WHERE id = {$formValues['template']}";
            }
            CRM_Core_DAO::executeQuery( $query, CRM_Core_DAO::$_nullArray );
        }
        if ( CRM_Utils_Array::value( 'update_format', $formValues ) ) {
            require_once 'CRM/Core/BAO/PdfFormat.php';
            $bao = new CRM_Core_BAO_PdfFormat();
            $bao->savePdfFormat( $formValues, $formValues['format_id'] );
        }

        $html = array();
        require_once 'CRM/Utils/Token.php';
        require_once 'CRM/Utils/TokenContrib.php';

        $tokens = array( );
        CRM_Utils_Hook::tokens( $tokens );
        $categories = array_keys( $tokens );        
				
        $html_message = $formValues['html_message'];
        
        //time being hack to strip '&nbsp;'
        //from particular letter line, CRM-6798 
        self::formatMessage( $html_message );

        require_once 'CRM/Activity/BAO/Activity.php';
		$messageToken = CRM_Activity_BAO_Activity::getTokens( $html_message );  

		$returnProperties = array();
        if( isset( $messageToken['contact'] ) ) { 
            foreach ( $messageToken['contact'] as $key => $value ) {
                $returnProperties[$value] = 1; 
            }
        }
                    
        require_once 'api/v2/utils.php';
        require_once 'CRM/Mailing/BAO/Mailing.php';

        $mailing = new CRM_Mailing_BAO_Mailing();
        if ( defined( 'CIVICRM_MAIL_SMARTY' ) &&
             CIVICRM_MAIL_SMARTY ) {
            require_once 'CRM/Core/Smarty/resources/String.php';
            civicrm_smarty_register_string_resource( );
        }

        $skipOnHold   = isset( $form->skipOnHold ) ? $form->skipOnHold : false;
        $skipDeceased = isset( $form->skipDeceased ) ? $form->skipDeceased : true;


        // group recurring contribution
        require_once 'api/api.php';
        $group_recurring_contribution = isset( $formValues['group_recurring_contribution'] ) ? $formValues['group_recurring_contribution'] : false;

        $recurSum = array();
        $contribRecur = array();
        $recurDone = array();
        if ($group_recurring_contribution) {
          foreach ($form->getVar('_contributionIds') as $item => $contributionId) {
            $result = civicrm_api("Contribution","get", array ('version' =>'3', 'id' => $contributionId));
            if ( !civicrm_error ( $result ) && isset($result['values'][$contributionId]['contribution_recur_id']) ) {
              
              $recurId = $result['values'][$contributionId]['contribution_recur_id'];
              $amount = floatVal($result['values'][$contributionId]['total_amount']);

              $contribRecur[$contributionId] = $recurId;

              // compute total amount for this recurring contribution
              if (!isset($recurSum[$recurId])) {
                $recurSum[$recurId] = $amount;
              } else {
                $recurSum[$recurId] += $amount;
              }
            }   
          }
        }
        watchdog('debug', 'recurSum -- <pre>' . print_r($recurSum, TRUE) . '</pre>');
        watchdog('debug', 'contribRecur -- <pre>' . print_r($contribRecur, TRUE) . '</pre>');

        // update dates ?
        $receipt_update = isset( $formValues['receipt_update'] ) ? $formValues['receipt_update'] : false;
        $thankyou_update = isset( $formValues['thankyou_update'] ) ? $formValues['thankyou_update'] : false;

        watchdog('debug', 'update dates -- ' . var_export($receipt_update, TRUE) . ' -- ' . var_export($thankyou_update, TRUE));

        $nowDate = date('YmdHis');

        foreach ($form->getVar('_contributionIds') as $item => $contributionId) {
            
            $recurId = isset($contribRecur[$contributionId]) ? $contribRecur[$contributionId] : false;
            if ($recurId) {
              $total_amount = $recurSum[$recurId];
            }

            // either not recurring or not grouping, either grouping and not done
            if ( !$recurId || !$recurDone[$recurId] ) {

              $contactId = civicrm_api("Contribution","getvalue", array ('version' =>'3', 'id' =>$contributionId, 'return' =>'contact_id'));
              $params  = array( 'contact_id'  => $contactId );

              list( $contribution ) = $mailing->getDetails($params, $returnProperties, $skipOnHold, $skipDeceased );

              if ( civicrm_error( $contribution ) ) {
                  $notSent[] = $contributionId;
                  continue;
              }

              $tokenHtml    = CRM_Utils_Token::replaceContactTokens( $html_message, $contribution[$contactId], true       , $messageToken);
              $tokenHtml    = CRM_Utils_Token::replaceHookTokens   ( $tokenHtml, $contribution[$contactId]   , $categories, true         );

              // added for contribution token replacement
              $contribution[$contactId]['contribution_id'] = $contributionId;
              if ($recurId) {
                // TODO : format amount
                $total_amount = $recurSum[$recurId];
                $contribution[$contactId]['recurring'] = true;
                $recurDone[$recurId] = true;
              } else {
                $total_amount = civicrm_api("Contribution","getvalue", array ('version' =>'3', 'id' =>$contributionId, 'return' =>'total_amount'));  
              }
              $contribution[$contactId]['total_amount'] = $total_amount;
              watchdog('debug', $contactId . ' -- ' . $contributionId . ' -- ' . print_r($contribution[$contactId], TRUE));

              $tokenHtml    = CRM_Utils_TokenContrib::replaceContributionTokens( $tokenHtml, $contribution[$contactId], true, $messageToken);
                
              if ( defined( 'CIVICRM_MAIL_SMARTY' ) &&
                   CIVICRM_MAIL_SMARTY ) {
            	  $smarty = CRM_Core_Smarty::singleton( );
            	  // also add the contact tokens to the template
            	  $smarty->assign_by_ref( 'contact', $contribution );
            	  $tokenHtml = $smarty->fetch( "string:$tokenHtml" );
              }

              $html[] = $tokenHtml;
 
            }

            // update dates (do it for each contribution including grouped recurring contribution)
            if ($receipt_update) {
              $results=civicrm_api("Contribution","update", array ('version' =>'3', 'id' => $contributionId, 'receipt_date' => $nowDate));
            }
            if ($thankyou_update) {
              $results=civicrm_api("Contribution","update", array ('version' =>'3', 'id' => $contributionId, 'thankyou_date' => $nowDate));
            }
            
        }
        
        require_once 'CRM/Activity/BAO/Activity.php';
        
        $session = CRM_Core_Session::singleton( );
        $userID = $session->get( 'userID' );         
        $activityTypeID = CRM_Core_OptionGroup::getValue( 'activity_type',
                                                          'Print PDF Letter',
                                                          'name' );
        $activityParams = array('source_contact_id'    => $userID,
                                'activity_type_id'     => $activityTypeID,
                                'activity_date_time'   => date('YmdHis'),
                                'details'              => $html_message,
                                );
        if( $form->_activityId ) {
            $activityParams  += array( 'id'=> $form->_activityId );
        }
        if( $form->_cid ) { 
            $activity = CRM_Activity_BAO_Activity::create( $activityParams );
        } else {
            // create  Print PDF activity for each selected contact. CRM-6886
            $activityIds = array();
            foreach ( $form->_contactIds as $contactId ) {
                $activityID = CRM_Activity_BAO_Activity::create( $activityParams );
                $activityIds[$contactId] = $activityID->id;
            }
        }
        
        foreach ( $form->_contactIds as $contactId ) {
            $activityTargetParams = array( 'activity_id'   => empty( $activity->id ) ? $activityIds[$contactId] : $activity->id ,
                                           'target_contact_id' => $contactId, 
                                           );
            CRM_Activity_BAO_Activity::createActivityTarget( $activityTargetParams );
        }
        
        
        require_once 'CRM/Utils/PDF/Utils.php';
        CRM_Utils_PDF_Utils::html2pdf( $html, "CiviLetter.pdf", false, $formValues );

        $form->postProcessHook( );

        CRM_Utils_System::civiExit( 1 );
    }//end of function

    
}


