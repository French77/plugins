<?php
/**
 * WedgeDesk
 *
 * Displays the interface for moving tickets between departments.
 *
 * @package wedgedesk
 * @copyright 2011 Peter Spicer, portions SimpleDesk 2010-11 used under BSD licence
 * @license http://wedgedesk.com/index.php?action=license
 *
 * @since 1.0
 * @version 1.0
 */

/**
 *	Displays the list of possible users a ticket can have assigned.
 *
 *	Will have been populated by shd_movedept() in WedgeDesk-MoveDept.php, adding into $context['dept_list'].
 *
 *	@see shd_movedept()
 *	@since 2.0
*/
function template_movedept()
{
	global $context, $txt;

	if (empty($context['shd_return_to']))
		$context['shd_return_to'] = 'ticket';

	// Back to the helpdesk.
	echo '
		<div class="pagesection">
			', template_button_strip(array($context['navigation']['back']), 'left'), '
		</div>
	<we:cat>
		<img src="', $context['plugins_url']['Arantor:WedgeDesk'], '/images/movedept.png">
		', $txt['shd_ticket_move_dept'], '
	</we:cat>
	<div class="roundframe">
		<form action="<URL>?action=helpdesk;sa=movedept2;ticket=', $context['ticket_id'], '" method="post" onsubmit="submitonce(this);">
			<div class="content">
				<dl class="settings">
					<dt>
						<strong>', $txt['shd_current_dept'], ':</strong>
					</dt>
					<dd>
						<a href="<URL>?', $context['shd_home'], ';dept=', $context['current_dept'], '">', $context['current_dept_name'], '</a>
					</dd>
					<dt>
						<strong>', $txt['shd_ticket_move_to'], ':</strong>
						<div class="smalltext">', $context['visible_move_dept'], '</div>
					</dt>
					<dd>
						<select name="to_dept">';

	foreach ($context['dept_list'] as $id => $name)
		echo '
							<option value="', $id, '"', ($id == $context['current_dept'] ? ' selected="selected"' : ''), '>', $name, '</option>';

	echo '
						</select>
					</dd>
				</dl>
				<dl class="settings">
					<dt>
						<strong>', $txt['shd_move_send_pm'], ':</strong>
					</dt>
					<dd>
						<input type="checkbox" name="send_pm" id="send_pm" checked="checked" onclick="document.getElementById(\'pm_message\').style.display = this.checked ? \'block\' : \'none\';" class="input_check">
					</dd>
				</dl>
				<fieldset id="pm_message">
					<dl class="settings">
						<dt>
							', $txt['shd_move_why'], '
						</dt>
						<dd>
							<textarea name="pm_content" rows="9" cols="70">', $txt['shd_move_dept_default'], '</textarea>
						</dd>
					</dl>
				</fieldset>
				<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '">';

	if ($context['shd_return_to'] == 'home')
		echo '
				<input type="hidden" name="home" value="1">';

	echo '
				<input type="submit" name="cancel" value="', ($context['shd_return_to'] == 'home' ? $txt['shd_cancel_home'] : $txt['shd_cancel_ticket']), '" accesskey="c" class="cancel">
				<input type="submit" value="', $txt['shd_ticket_move'], '" onclick="return submitThisOnce(this);" accesskey="s" class="submit">
			</div>
		</form>
	</div>';
}

?>