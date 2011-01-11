<? ob_start(); ?>
<table cellspacing="0" cellpadding="0" border="0" class="editTable">
	<tr>
		<td class="editLabel">Additional Privileges</td>
		<td class="editField">
			<ul class="boxlines" id="extraprivileges">
				<? if ($userperms) {
					foreach ($userperms as $perm) { ?>
						<li>
							<input type="hidden" class="novalidate" name="_permissions[]" value="<?= $perm['token'] ?>" />
							<a href="#" onclick="this.parentNode.parentNode.removeChild(this.parentNode); return false;"><img style="vertical-align: middle;" src="<?= $admin->mediaRoot ?>/images/red_delete.gif" /></a>
							<?= $perm['desc'] ?>
						</li>
					<? }
				} ?>
			</ul>
			<? if (!count($userperms)) { ?>
				<div id="noprivileges">
					No additional privileges have been granted to this user.
				</div>
			<? } ?>
			<hr />
			<i>Add new privilege:</i><br />
			<ol>
				<li style="padding-bottom: 5px;">
					Select a module:
					<select class="novalidate" id="perm_modules" name="_perm_modules" size="1" style="width:250px" onchange="selectModule(this, <? if ($object->customer) echo $object->customer->pk; else echo '0'; ?>)">
						<option value="">Select a module</option>
						<? foreach ($modules as $mod) {
							if ($mod->checkAccess()) { ?>
								<option value="<?= $mod->permission ?>"><?= $mod->name ?></option>
							<? }
						} ?>
					</select>
				</li>
				<li style="padding-bottom: 5px;">
					Select a section:
					<select class="novalidate" id="perm_types" name="_perm_types" size="1" style="width:250px" disabled="true" onchange="selectType(this, <? if ($object->customer) echo $object->customer->pk; else echo '0'; ?>)">
						<option value="">Please select a module first</option>
					</select>
				</li>
				<li style="padding-bottom: 5px;">
					Select item(s) and privilege(s):<br />
					<select class="novalidate" id="perm_instances" multiple="true" name="_perm_instances" size="10" style="width:450px" disabled="true">
						<option value="">Please select a module and section first</option>
					</select>
					<br />
					Privileges:
					<span id="perm_actions_container">
						<? foreach ($permactions as $actperm => $action) { ?>
							<input class="novalidate" style="vertical-align: middle;" type="checkbox" name="_perm_actions" value="<?= $actperm ?>" disabled="true"/> <?= $action ?>
						<? } ?>
					</span>
				</li>
				<li><input type="button" onclick="addPermission(this.form)" value="Add Privilege" /></li>
			</ol>
		</td>
	</tr>
</table>
<?
$extraForm = ob_get_contents();
ob_end_clean();

echo $module->fetchTemplate('object_form.tpl',
	array_merge($context, array('extraForm' => $extraForm)));
