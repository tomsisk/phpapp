<?
$relModel = $field->getRelationModel();
$fieldAdmin = $admin->findModelAdmin($relModel);
$orderField = $modeladmin->getOrderField($relModel);

if (!$inlineField && $modeladmin->isInlineObject($field->field)) {
	$selector = $modeladmin->inlineObjects[$field->field]['selector'];
	if (isset($relatedFieldErrors[$field->field])) { ?>
		<div class="error">
			<b>Unable to save some <?= htmlentities(strtolower($field->pluralName)) ?></b><br />
			Please correct the highlighted fields below. Click on a field to view the error.
		</div>
		<br />
	<? }

	if ($orderField) { ?>
		<div class="tablegroup">
	<? } ?>
	<table class="listTable tablegroupmember">
		<?
		$fieldList = $fieldAdmin->getFlattenedFieldGroups();
		ob_start();
		?>
		<tr>
			<? if ($orderField) { ?>
				<th width="15">&nbsp;</th>
			<? } ?>
			<th width="15">&nbsp;</th>
			<?
			$rfieldct = 0;
			foreach ($fieldList as $rfieldName) {
				$rfield = $relModel->_fields[$rfieldName];
				if (($rfield->options['editable'] || $rfield->options['readonly']) && $rfield->field != $field->joinField) {
					$rfieldct++;
					?>
					<th valign="top">
						<?= htmlentities($rfield->name) ?>
						<? if (isset($rfield->options['help'])) { ?>
							<span class="fieldHelp">(<?= $rfield->options['help'] ?>)</span>
						<? } ?>
					</th>
				<? }
			}
			if ($selector) {
				$rfieldct++;
				?>
				<th><?= $selector[0] ?></th>
			<? } ?>
		</tr>
		<?
		if ($rfieldct > 1)
			ob_end_flush();
		else
			ob_end_clean();
		if ($orderField) { ?>
			</table>
			<ul class="plain sortablehandle" id="<?= $module->id ?>__<?= $fieldAdmin->id ?>__<?= $orderField->field ?>">
		<? }
		$objetList = null;
		if (isset($relatedObjects[$field->field]))
			$objectList = $relatedObjects[$field->field];
		else
			$objectList = $object[$field->field];

		if (!count($objectList)) {
			if ($orderField) { ?>
				<li><table class="listTable tablegroupref tablegroupmember">
			<? } ?>
			<tr class="odd">
				<? if ($orderField) { ?>
					<td width="15" style="padding-top: 6px">
						<img class="dragHandle" title="Drag to change order" src="<?= $admin->mediaRoot ?>/images/move_icon.png" />
					</td>
				<? } ?>
				<td width="15" style="padding-top: 6px">
					<? if ($orderField) { ?>
					<a href="" onclick="removeListRow(this.up('li')); return false;"><img src="<?= $admin->mediaRoot ?>/images/red_delete.gif"/></a>
					<? } else { ?>
					<a href="" onclick="removeInlineRow(this.up('tr')<? if ($rfieldct == 1) { ?>, true<? } ?>); return false;"><img src="<?= $admin->mediaRoot ?>/images/red_delete.gif"/></a>
					<? } ?>
					<input type="hidden" name="_<?= $field->field ?>_pk[]" value="" />
				</td>
				<? foreach ($fieldList as $rfieldName) {
					$rfield = $relModel->_fields[$rfieldName];
					if (($rfield->options['editable'] || $rfield->options['readonly']) && $rfield->field != $field->joinField) { ?>
						<td>
						<? if (($rfield->options['default'] || !$rfield->options['required']) && $rfield->options['readonly']) { ?>
							<?= $rfield->options['default'] ?>
						<? } else { ?>
							<?= $modeladmin->getFieldInputHtml($relModel, $rfield, null, null, null, $field->field) ?><br />
							<span class="fielderror__<?= $field->field ?>_<?= $rfield->field ?>"></span>
						<? } ?>
						</td>
					<? }
				}
				if ($selector) { ?>
					<td>
						<input type="radio" name="~<?= $selector[1] ?>" value="0" onclick="setValueToIndex(this)" />
					</td>
				<? } ?>
			</tr>
			<? if ($orderField) { ?>
				</table></li>
			<? }
		} else {
			$idx = 0;
			$row = 'odd';
			foreach ($objectList as $related) {
				if ($orderField) { ?>
					<li class="<?= $row ?>" id="_<?= $field->field ?>_sortpk_<?= $related->pk ?>"><table class="listTable tablegroupref tablegroupmember">
				<? }
				if (isset($relatedErrors[$field->field][$idx])) { ?>
					<tr class="< if (!$orderField) { echo $row.' ';?>noborder">
						<? if ($orderField) { ?>
							<td width="15">&nbsp;</td>
						<? } ?>
						<td width="15">&nbsp;</td>
						<td colspan="<?= $rfieldct ?>">
							<span id="errorMessage_<?= $field->field ?>">
								<div class="errorSmall">
									<? foreach ($relatedErrors[$field->field][$idx] as $error) {
										echo htmlentities($error).'<br />';
									} ?>
								</div>
							</span>
						</td>
					</tr>
				<? } ?>
				<tr class="<? if (!$orderField) echo $row; ?>">
					<? if ($orderField) { ?>
						<td width="15" style="padding-top: 6px">
							<img class="dragHandle" title="Drag to change order" src="<?= $admin->mediaRoot ?>/images/move_icon.png" />
						</td>
					<? } ?>
					<td width="15" style="padding-top: 6px">
						<? if ($orderField) { ?>
						<a href="" onclick="removeListRow($(this).up('li')); return false;"><img src="<?= $admin->mediaRoot ?>/images/red_delete.gif"/></a>
						<? } else { ?>
						<a href="" onclick="removeInlineRow($(this).up('tr')<? if ($rfieldct == 1) { ?>, true<? } ?>); return false;"><img src="<?= $admin->mediaRoot ?>/images/red_delete.gif"/></a>
						<? } ?>
						<input type="hidden" name="_<?= $field->field ?>_pk[]" value="<?= $related->pk ?>" />
						<input type="hidden" name="_<?= $field->field ?>_<?= $field->joinField ?>[]" value="<?= $object->pk ?>" />
					</td>
					<?
					foreach ($fieldList as $rfieldName) {
						$rfield = $relModel->_fields[$rfieldName];
						if (((isset($rfield->options['editable']) && $rfield->options['editable']) || isset($rfield->options['readonly']) && $rfield->options['readonly']) && $rfield->field != $field->joinField) { ?>
							<td>
							<?
							if (($related->pk || !isset($rfield->options['required']) || !$rfield->options['required'] || isset($rfield->options['default'])) && (isset($rfield->options['readonly']) && $rfield->options['readonly'])) {
								if ($fieldAdmin->checkAction('VIEW'))
									echo '<a href="'.$fieldAdmin->getRelativeUrl('/view/'.$related->pk).'">';
								echo $modeladmin->getFieldValueHTML($related, $rfield->field);
								if ($fieldAdmin->checkAction('VIEW'))
									echo '</a>';
							} else {
								$inputTitle = null;
								$inputClass = null;
								if (isset($relatedFieldErrors[$field->field][$idx][$rfield->field])) {
									$inputTitle = $relatedFieldErrors[$field->field][$idx][$rfield->field][0];
									$inputClass = 'inputError';
								}
								echo $modeladmin->getFieldInputHTML($related, $rfield, null, $inputClass, $inputTitle, $field->field, $idx);
							} ?>
							</td>
						<? }
					}
					if ($selector) { ?>
						<td>
							<input type="radio" name="~<?= $selector[1] ?>" value="<?= $idx ?>"
								onclick="setValueToIndex(this)"
								<? if ($object[$selector[1]] == $related[$selector[2]]) echo 'checked'; ?>
								/>
						</td>
					<? } ?>
				</tr>
				<? if ($orderField) { ?>
					</table></li>
				<? }
				$idx++;
				$row = $row == 'odd' ? 'even' : 'odd';
			}
		} ?>
		<? if ($orderField) { ?>
				</ul>
				<div style="padding-top: 5px;">
					<a href="#" title="Add a new <?= htmlentities(strtolower($this->singularize($field->name))) ?>" onclick="cloneLastChild(this.parentNode.parentNode.select('ul').first()); return false;"><img src="<?= $admin->mediaRoot ?>/images/blue_add.gif"/></a>
					<i>Add a new <?= htmlentities(strtolower($this->singularize($field->name))) ?></i>
				</div>
			</div>
			<hr />
		<? } else { ?>
				<tr>
					<td><a href="" title="Add a new <?= htmlentities(strtolower($this->singularize($field->name))) ?>" onclick="clonePreviousRow(this.parentNode.parentNode); return false;"><img src="<?= $admin->mediaRoot ?>/images/blue_add.gif"/></a></td>
					<td colspan="<?= $rfieldct ?>"><i>Add a new <?= htmlentities(strtolower($this->singularize($field->name))) ?></i></td>
				</tr>
			</table>
		<? }
} else {
	if ($object->pk) {

		if ($orderField)
			echo '<ul class="boxlines sortable" style="width: 50%;" id="'.$module->id.'__'.$fieldAdmin->id.'__'.$orderField->field.'">';

		$rquery = $object[$field->field];
		$hasmore = false;

		$maxshown = isset($modeladmin->fieldOptions[$field->field]['maxShown'])
			? $modeladmin->fieldOptions[$field->field]['maxShown']
			: 0;

		if ($maxshown > 0 && $rquery->count() > $maxshown) {
			$rquery = $rquery->slice($maxshown);
			$hasmore = true;
		}

		foreach ($rquery as $related) {
			$canmod = $fieldAdmin->checkPermission('MODIFY', $related->pk);
			if ($orderField) {
				echo '<li id="_'.$field->field.'_sortpk_'.$related->pk.'">';
				if ($fieldAdmin->checkPermission('DELETE', $related->pk)) {
					echo '<div style="float:right;">';
					echo '<a href="'.$fieldAdmin->relativeUrl('/delete/'.$related->pk).'" ';
					echo 'onclick="return confirm(\'Are you sure you want to delete this '.htmlentities(strtolower($fieldAdmin->name)).'?\');" ';
					echo '>';
					echo '<img src="'.$admin->mediaRoot.'/images/red_delete.gif" />';
					echo '</a>';
					echo '</div>';
				}
				echo '<img class="dragHandle" title="Drag to change order" src="'.$admin->mediaRoot.'/images/move_icon.png" />&nbsp;';
			} else if ($fieldAdmin->checkPermission('DELETE', $related->pk)) {
				echo '<a href="'.$fieldAdmin->relativeUrl('/delete/'.$related->pk).'">';
				echo '<img src="'.$admin->mediaRoot.'/images/red_delete.gif" />';
				echo '</a>';
			}
			if ($canmod)
				echo '<a href="'.$fieldAdmin->relativeUrl('/edit/'.$related->pk).'">';
			else
				echo '<a href="'.$fieldAdmin->relativeUrl('/view/'.$related->pk).'">';
			echo htmlentities($this->toString($related));
			echo '</a>';
			if ($orderField) {
				echo '</li>';
			} else {
				echo '<br/>';
			}
		}
		if ($orderField)
			echo '</ul>';
		if ($hasmore)
			echo '<i>There are more results then can be displayed on this page.</i>';
		if ($fieldAdmin->checkPermission('CREATE')) {
			echo '<div style="padding-top: 5px;">';
			echo '<a href="'.$fieldAdmin->relativeUrl('/add');
			if ($orderField) {
				echo '?'.$orderField->groupBy[0].'='.$object->pk;
			}
			echo '">';
			echo '<img src="'.$admin->mediaRoot.'/images/blue_add.gif" />';
			echo '</a>';
			echo ' <a href="'.$fieldAdmin->relativeUrl('/add');
			if ($orderField) {
				echo '?'.$orderField->groupBy[0].'='.$object->pk;
			}
			echo '">';
			echo '<i>Add a new '.strtolower($fieldAdmin->name).'</i>';
			echo '</a>';
			echo '</div>';
		}
	} else {
		echo '<i>You must save this entry first before adding '.strtolower($fieldAdmin->pluralName).' to it';
	}
}
