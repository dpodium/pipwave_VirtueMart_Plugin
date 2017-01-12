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
// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');

JFormHelper::loadFieldClass('list');

class JFormFieldGetGroups extends JFormFieldList {

    protected $type = 'getgroups';

    /*
     * Get shopper groups for payment processing fees configuration
     * for pipwave
     */

    public function getOptions() {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $query->select('virtuemart_shoppergroup_id,shopper_group_name,published')->from('#__virtuemart_shoppergroups');
        $db->setQuery($query);

        $result = $db->loadObjectList();
        $groups = array();
        foreach ($result as $key => $row) {
            if ($row->published == 1) {
                $groups[$key]['text'] = vmText::_($row->shopper_group_name);
                $groups[$key]['value'] = $row->virtuemart_shoppergroup_id;
            }
        }
        // Put "Select an option" on the top of the list.
        array_unshift($groups, JHtml::_('select.option', '0', JText::_('Select an option')));
        // Merge any additional options in the XML definition.
        $options = array_merge(parent::getOptions(), $groups);
        return $options;
    }

}
