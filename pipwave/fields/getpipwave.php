<?php

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
