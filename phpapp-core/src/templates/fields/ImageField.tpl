<? if ($field->options['options']) { ?>
<select
	<? if ($inlineField) { ?>
	name="_<?= $inlineField ?>_<?= $field->field ?>[]"
	<? } elseif ($fieldName) { ?>
	name="<?= $fieldName ?>"
	<? } else { ?>
	name="<?= $field->field ?>"
	<? } ?>
	onchange="fieldChanged(this)"
	<? if ($inputTitle) { ?>title="<?= $inputTitle ?>"<? } ?>
	<? if ($inputClass) { ?>class="<?= $inputClass ?>"<? } ?>
	>
	<? if (!$field->options['required'] && !isset($field->options['options'][''])) { ?>
		<option value="">None</option>
	<? } ?>
	{foreach from=$field->options['options'] key=option item=description) { ?>
		<option <? if ($object[$field->field] == $option) echo 'selected'; ?> value="<?= $option ?>"><?= htmlentities($description) ?></option>
	{/foreach}
</select>
<? } else { ?>
<script language="javascript" src="<?= $admin->mediaRoot ?>/js/lib/tinybrowser/tb_standalone.js.php"></script>
<input type="text"
	class="editInput<? if ($inputClass) echo ' '.$inputClass; ?>"
	<? if ($inputTitle) { ?>title="<?= $inputTitle ?>"<? } ?>
	<? if ($inlineField) { ?>
	name="_<?= $inlineField ?>_<?= $field->field ?>[]"
	<? } elseif ($fieldName) { ?>
	name="<?= $fieldName ?>"
	<? } else { ?>
	name="<?= $field->field ?>"
	<? } ?>
	value="<?= htmlentities($object[$field->field]) ?>"
	maxlength="<?= $field->length ?>"
	onchange="fieldChanged(this); document.getElementById('_imagefield_<?= $fieldName ?>img').src = this.value ? '<?= $admin->baseUrl ?>/thumb/'+this.value : '<?= $admin->mediaRoot ?>/images/spacer.gif';"
<? if ($field->length < 40) { ?>
	size="<?= $field->length ?>"
<? } else { ?>
	style="width:300px"
<? } ?>
	id="_imagefield_<?= $fieldName ?>"
	/>
	<a href="#" onclick="tinyBrowserPopUp('image', '_imagefield_<?= $fieldName ?>'); return false;"><img src="<?= $admin->mediaRoot ?>/images/filechooser.png" /></a>
	<br />
	<img class="imagepreview" src="<? if ($object[$field->field]) { ?><?= $admin->baseUrl ?>/thumb/<?= $object[$field->field] ?><? } else { ?><?= $admin->mediaRoot ?>/images/spacer.gif<? } ?>" id="_imagefield_<?= $fieldName ?>img" />
<? } ?>
