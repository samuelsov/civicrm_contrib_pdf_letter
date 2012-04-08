<?php

/*
 +--------------------------------------------------------------------+
 | CiviCRM version 3.4                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2011                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2011
 * $Id$
 *
 */

require_once 'CRM/Contribute/Form/Task.php';
require_once 'CRM/Contact/Form/Task/PDFLetterCommon.php';
require_once 'CRM/Contribute/Form/Task/PDFLetterCommon.php';

require_once 'CRM/Core/Menu.php';
require_once 'CRM/Core/BAO/CustomGroup.php';
require_once 'CRM/Contact/BAO/Contact.php';
require_once 'CRM/Activity/BAO/Activity.php';
/**
 * This class provides the functionality to create PDF letter for a group of
 * contacts or a single contact. 
 */
class CRM_Contribute_Form_Task_PDFLetter extends CRM_Contribute_Form_Task {
    /**
     * all the existing templates in the system
     *
     * @var array
     */
    public $_templates = null;
	
    public $_single    = null;
	
    public $_cid       = null;

	
    /**
     * build all the data structures needed to build the form
     *
     * @return void
     * @access public
     */
    
    function preProcess( ) {
     
        $this->skipOnHold = $this->skipDeceased = false;
        CRM_Contact_Form_Task_PDFLetterCommon::preProcess( $this );

        // store case id if present
        $this->_caseId = CRM_Utils_Request::retrieve( 'caseid', 'Positive', $this, false );

        // retrieve contact ID if this is 'single' mode
        $cid = CRM_Utils_Request::retrieve( 'cid', 'Positive', $this, false );
        
        $this->_activityId = CRM_Utils_Request::retrieve( 'id', 'Positive', $this, false );
        
        if ( $cid ) {
            CRM_Contact_Form_Task_PDFLetterCommon::preProcessSingle( $this, $cid );
            $this->_single = true;
            $this->_cid = $cid;
        } else {
            parent::preProcess( );
        }
        $this->assign( 'single', $this->_single );

    }
    function setDefaultValues( ) 
    {
        $defaults = array();
        if ( isset( $this->_activityId ) ) {
            $params = array( 'id' => $this->_activityId );
            CRM_Activity_BAO_Activity::retrieve( $params, $defaults );
            $defaults['html_message'] = $defaults['details'];
        }
        $defaults = $defaults + CRM_Contact_Form_Task_PDFLetterCommon::setDefaultValues( );
        return $defaults;        
    }
    
    /**
     * Build the form
     *
     * @access public
     * @return void
     */
    public function buildQuickForm()
    {
        //enable form element
        $this->assign( 'suppressForm', false );

        // use contact form as a base
        CRM_Contact_Form_Task_PDFLetterCommon::buildQuickForm( $this );

        // specific need for contributions
        //$this->add('thank_you_date', 'date_thank_you', ts('Thank you date'), CRM_Core_SelectValues::date( null, 'Y M d' ), false);
        $this->add( 'checkbox', 'receipt_update', ts('Update receipt date for those contributions'), false ) ;
        $this->add( 'checkbox', 'thankyou_update', ts('Update thank you date for those contributions'), false );
        $this->add( 'checkbox', 'group_recurring_contribution', ts('Group recurring contribution (1 letter by recurring contribution for the choosen period)'), false );
        
    }


    /**
     * process the form after the input has been submitted and validated
     *
     * @access public
     * @return None
     */
    public function postProcess() {
        // TODO: rewrite using contribution token and one letter by contribution
	$this->setContactIDs();

        CRM_Contribute_Form_Task_PDFLetterCommon::postProcess( $this );
    }

}


