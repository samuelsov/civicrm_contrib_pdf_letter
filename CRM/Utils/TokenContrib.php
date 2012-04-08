<?php

require_once 'CRM/Utils/Token.php';


/**
 * Class to extends CRM_Utils_Token for contribution 
 */
class CRM_Utils_TokenContrib extends CRM_Utils_Token
{
    static $_tokens = array(
                             'contribution'  => array('contribution_id', 'total_amount', 'receive_date'),
                           );

    /**
     * private in CRM_Utils_Token - need to redeclare :(
     */
    protected static function tokenRegex($token_type)
    {
        return '/(?<!\{|\\\\)\{'.$token_type.'\.([\w]+(\-[\w\s]+)?)\}(?!\})/e';
    }

    protected static function tokenEscapeSmarty($string)
    {
        return preg_replace(array('/{/', '/(?<!{ldelim)}/'), array('{ldelim}', '{rdelim}'), $string);
    }
    
    public static function &replaceContributionTokens($str, &$contribution, $html = false, $knownTokens = null, $escapeSmarty = false)
    {

        $key = 'contribution';

        $str = preg_replace( self::tokenRegex($key),
                             'self::getContributionTokenReplacement(\'\\1\',$contribution,$html,$escapeSmarty)',
                             $str);
 
        return $str;
    }

    public static function getContributionTokenReplacement($token, &$contribution, $html = false, $escapeSmarty = false)
    {
        switch ( $token ) {
        case 'total_amount':
            //watchdog('debug', 'replacement -- <pre>' . print_r($contribution, TRUE) . '</pre>');
            //$value = civicrm_api("Contribution","getvalue", array ('version' =>'3', 'id' =>$contribution['contribution_id'], 'return' =>'total_amount'));
            $value = $contribution['total_amount'];
            //watchdog('debug', 'total -- <pre>' . $contribution['contribution_id']  . ' -- ' . print_r($value, TRUE) . '</pre>');
            break;

        case 'contribution_id':
            $value = $contribution['contribution_id'];
            break;

        case 'receive_date':
            $value = civicrm_api("Contribution","getvalue", array ('version' =>'3', 'id' =>$contribution['contribution_id'], 'return' =>'receive_date'));
            if ($contribution['recurring']) {
              $value = date('Y-m-d', mktime(0, 0, 0, 12, 31, substr($value, 0, 4)));
            } else {
              $value = civicrm_api("Contribution","getvalue", array ('version' =>'3', 'id' =>$contribution['contribution_id'], 'return' =>'receive_date'));
              $value = substr($value, 0, 10);
            }
            $value = CRM_Utils_Date::customFormat($value);
            
            break;

        }
    

        if ( $escapeSmarty ) {
            $value = self::tokenEscapeSmarty( $value );
        }
        return $value;

    }

    
}



