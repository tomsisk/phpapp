<? ob_start(); ?>
<h3>Role Permissions</h3>
<div class="highlightable" >
	<input type="checkbox" class="novalidate" value="true" id="_perm_ALL_ALL_ALL" name="_perm_ALL_ALL_ALL" <? if ($roleperms['ALL']['ALL']['ALL']) echo 'checked'; ?>/>
	<b>All Permissions</b>
	<? foreach ($modules as $mod) { ?>
		<div class="highlightable" style="padding-left:10px;">
			<div class="treelist">
				<div>
					<table cellspacing="0" cellpadding="0">
						<tr>
							<td width="185">
								<div style="padding:3px;">
									<input type="checkbox" class="novalidate" value="true" id="_perm_<?= $mod->permission ?>_ALL_ALL" name="_perm_<?= $mod->permission ?>_ALL_ALL" <? if ($roleperms[$mod->permission]['ALL']['ALL']) echo 'checked'; ?>/>
									<label for="_perm_<?= $mod->permission ?>_ALL_ALL"><b><?= $mod->name ?></b></label>
								</div>
							</td>
							<td>
								<table cellpadding="0" cellspacing="0" border="0">
									<tr>
									<? foreach ($permactions as $actperm => $action) { ?>
										<td class="highlightable">
											<div style="padding:3px;">
												<input type="checkbox" class="novalidate" value="true" id="_perm_<?= $mod->permission ?>_ALL_<?= $actperm ?>" name="_perm_<?= $mod->permission ?>_ALL_<?= $actperm ?>" <? if ($roleperms[$mod->permission]['ALL'][$actperm]) echo 'checked'; ?>/>
												<label for="_perm_<?= $mod->permission ?>_ALL_<?= $actperm ?>"><?= $action ?></label>
											</div>
										</td>
									<? } ?>
									</tr>
								</table>
							<td>
						</tr>
					</table>
				</div>
				<div class="hidden">
					<table cellspacing="0" cellpadding="0">
						<? foreach ($mod->modelAdmins as $modad) { ?>
							<tr class="highlightable">
								<td width="185">
									<div style="margin-left: 25px;">
										<input type="checkbox" class="novalidate" value="true" id="_perm_<?= $mod->permission ?>_<?= $modad->permission ?>_ALL" name="_perm_<?= $mod->permission ?>_<?= $modad->permission?>_ALL" <? if ($roleperms[$mod->permission][$modad->permission]['ALL']) echo 'checked'; ?>/>
										<label for="_perm_<?= $mod->permission?>_<?= $modad->permission ?>_ALL"><?= $modad->getName() ?></label>
									</div>
								</td>
								<td>
									<table cellpadding="0" cellspacing="0" border="0">
										<tr>
										<? foreach ($permactions as $actperm => $action) { ?>
											<td class="highlightable">
												<div style="padding:3px;">
													<input type="checkbox" class="novalidate" value="true" id="_perm_<?= $mod->permission ?>_<?= $modad->permission ?>_<?= $actperm ?>" name="_perm_<?= $mod->permission ?>_<?=$modad->permission ?>_<?= $actperm ?>" <? if ($roleperms[$mod->permission][$modad->permission][$actperm]) echo 'checked'; ?>/>
													<label for="_perm_<?= $mod->permission?>_<?= $modad->permission ?>_<?= $actperm ?>"><?= $action ?></label>
												</div>
											</td>
										<? } ?>
										</tr>
									</table>
								</td>
							</tr>
						<? } ?>
					</table>
					<hr />
				</div>
			</div>
		</div>
	<? } ?>
</div>
<br />
<?
$extraForm = ob_get_contents();
ob_end_clean();

$context['extraForm'] = $extraForm;
echo $module->fetchTemplate('object_form.tpl', $context);
