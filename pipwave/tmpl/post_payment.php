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
defined('_JEXEC') or die();
?>
<table>
    <tr class="post_payment_order_number">
        <td class="post_payment_order_number_title" style="width: 50%"><?php echo vmText::_('COM_VIRTUEMART_ORDER_NUMBER'); ?></td>
        <td><?php echo $viewData["order_number"]; ?></td>
    </tr>
    <tr class="post_payment_order_total">
        <td class="post_payment_order_total_title" style="width: 50%"><?php echo vmText::_('COM_VIRTUEMART_ORDER_PRINT_TOTAL'); ?></td>
        <td><?php echo $viewData["displayTotalInPaymentCurrency"]; ?></td>
    </tr>
</table>

<a class="vm-button-correct" href="<?php echo JRoute::_('index.php?option=com_virtuemart&view=orders&layout=details&order_number=' . $viewData["order_number"] . '&order_pass=' . $viewData["order_pass"], false) ?>"><?php echo vmText::_('COM_VIRTUEMART_ORDER_VIEW_ORDER'); ?></a>






