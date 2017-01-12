<?php

/**
 * pipwave Payment Plugin
 * 
 * @package VirtueMart
 * @subpackage payment
 * @version $Id$
 * @author pipwave Development Team
 * @copyright (C) 2016 pipwave, a division of Dynamic Podium. All rights reserved
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See /administrator/components/com_virtuemart/COPYRIGHT.php for copyright notices and details.
 *
 * http://virtuemart.net
 */

defined('JPATH_BASE') or die();

jimport('joomla.form.formfield');

class JFormFieldGetPipwave extends JFormField {

    /**
     * Element name
     *
     * @access    protected
     * @var        string
     */
    var $type = 'getpipwave';

    protected function getInput() {
        $url = "https://www.pipwave.com";
        $logo = "<img src=" . JURI::root() . "images/stories/virtuemart/payment/pipwave.png" . " />";
        $html .= '<p><a target="_blank" href="' . $url . '"  >' . $logo . '</a></p>';

        return $html;
    }

}
