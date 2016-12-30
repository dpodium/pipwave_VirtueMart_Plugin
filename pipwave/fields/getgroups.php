<?php

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');

JFormHelper::loadFieldClass('list');

class JFormFieldGetGroups extends JFormFieldList {

    protected $type = 'getgroups';

    public function getOptions() {
        $db = JFactory::getDbo();
        $query = $db->getQuery(true);
        $query->select('virtuemart_shoppergroup_id,shopper_group_name,published')->from('#__virtuemart_shoppergroups');
        $db->setQuery($query);

        $result = $db->loadObjectList();
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
