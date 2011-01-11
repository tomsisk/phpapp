<? if ($modeladmin->checkPermission('CREATE')) { ?>
	<span class="nextpage" style="float:right;"><a href="<?= $sectionurl ?>/add/?<?= $filterstr ?>">Add new <?= htmlentities(strtolower($modeladmin->name)) ?></a></span>
<? }
$candelete = $modeladmin->checkAction('DELETE');
$canedit = $modeladmin->checkAction('MODIFY');
?>
<h1><?= htmlentities(ucwords($modeladmin->pluralName)) ?></h1>

<table cellspacing="0" cellpadding="0" class="filterTable">
	<tr>
		<td class="list">
			<? if ($objectcount) {
				if ($modeladmin->paginated) { ?>
					<div class="pagelist">
						<div class="pagebuttons">
							Results per page:
							<? if ($_REQUEST['_r'] === null or $_REQUEST['_r'] == 25) { ?><b>25</b><? } else { ?><a href="<?= $this->selfurl('_r', '25') ?>">25</a><? } ?> |
							<? if ($_REQUEST['_r'] == 50) { ?><b>50</b><? } else { ?><a href="<?= $this->selfurl('_r', '50') ?>">50</a><? } ?> |
							<? if ($_REQUEST['_r'] == 100) { ?><b>100</b><? } else { ?><a href="<?= $this->selfurl('_r', '100') ?>">100</a><? } ?>
						</div>
						Displaying <?= $pagestart ?> - <?= $pageend ?> of <?= $objectcount ?> <?= htmlentities(strtolower($modeladmin->pluralName)) ?>
						<br clear="all"/>
					</div>
				<? }
				if ($modeladmin->sortable && $canedit) { ?>
					<div class="tablegroup">
				<? } ?>
				<table cellspacing="0" cellpadding="0" class="listTable tablegroupmember">
					<tr>
						<? if ($modeladmin->sortable && $canedit) { ?>
							<th width="15">&nbsp;</th>
						<? }
						if ($candelete) { ?>
						<th width="15">&nbsp;</th>
						<? }
						if (!count($displayFields)) { ?>
							<th>Name</th>
						<? } else {
							foreach ($displayFields as $field) {
								if (!$modeladmin->isAccessFiltered($field)) {
									if ($sort == $field) {
										if ($sortdir == '-')
											$sf = $field;
										else
											$sf = '-'.$field;
									} else {
										$sf = $field;
									} ?>
									<th>
										<a href="<?= $this->selfurl('_s', $sf) ?>">
											<?= htmlentities($modeladmin->getFieldName($field)) ?>
											<? if ($sort == $field) {
												if ($sortdir == '+') { ?>
													<img src="<?= $admin->mediaRoot ?>/images/sort_asc.png" />
												<? } else { ?>
													<img src="<?= $admin->mediaRoot ?>/images/sort_desc.png" />
												<? }
											} ?>
										</a>
									</th>
								<? }
							}
						} ?>
						<? if ($modeladmin->previewUrl) { ?>
							<th>&nbsp;</th>
						<? } ?>
					</tr>
					<? if ($modeladmin->sortable && $canedit) { ?>
						</table>
						<ul class="plain sortablehandle" id="<?= $module->id ?>__<?= $modeladmin->id ?>__<?= $modeladmin->sortable ?>">
					<? }
					$row = 'odd';
					foreach ($objects as $object) {
						$canedititem = $modeladmin->checkPermission('MODIFY', $object->pk);
						$candeleteitem = $modeladmin->checkPermission('DELETE', $object->pk);
						if ($modeladmin->sortable && $canedit) { ?>
							<li id="<?= $modeladmin->id ?>_<?= $object->pk ?>"><table class="listTable tablegroupref tablegroupmember">
						<? } ?>
						<tr class="<?= $row ?>">
							<? if ($modeladmin->sortable && $canedit) { ?>
								<td width="15" style="padding-top: 6px">
									<img class="dragHandle" title="Drag to change order" src="<?= $admin->mediaRoot ?>/images/move_icon.png" />
								</td>
							<? }
							if ($candelete) { ?>
							<td width="15">
								<? if ($candeleteitem) { ?>
									<a <? if ($modeladmin->deleteConfirm) { ?>
											href="<?= $sectionurl ?>/deleteconfirm/<?= $object->pk ?>?<?= $filterstr ?>"
										<? } else { ?>
											href="<?= $sectionurl ?>/delete/<?= $object->pk ?>?<?= $filterstr ?>"
											onclick="return confirm('Are you sure you want to permanently delete this item?')"
										<? } ?>
										><img src="<?= $admin->mediaRoot ?>/images/red_delete.gif" title="Delete" alt="Delete" border="0" /></a>
								<? } else { ?>
									<img src="<?= $admin->mediaRoot ?>/images/red_delete_disabled.gif" title="Deletion not allowed" alt="Deletion not allowed" border="0" />
								<? } ?>
							</td>
							<? }
							if (!count($displayFields)) { ?>
								<td><a href="<?= $sectionurl ?>/<? if ($canedititem) echo 'edit'; else echo 'view'; ?>/<?= $object->pk ?>?<?= $filterstr ?>"><?= htmlentities($object) ?></a></td>
							<? } else {
								$first = true;
								foreach ($displayFields as $field) {
									if (!$modeladmin->isAccessFiltered($field)) {
										$fieldobj = $modeladmin->findField($field); ?>
										<td>
											<? if ($first && ($fieldobj && $fieldobj->field != $object->_idField || count($displayFields) == 1)) {
												$first = false; ?>
												<a href="<?= $sectionurl ?>/<? if ($canedititem) echo 'edit'; else echo 'view'; ?>/<?= $object->pk ?>?<?= $filterstr ?>"><?= $this->defaultValue($modeladmin->getFieldValueHTML($object, $field, 'text'), $modeladmin->name) ?></a>
											<? } else { ?>
												<?= $modeladmin->getFieldValueHTML($object, $field) ?>
											<? } ?>
										</td>
									<?  }
								}
							}
							if ($modeladmin->previewUrl) { ?>
								<td align="right">
									<a target="_blank" href="<?= eval('?>'.$modeladmin->previewUrl) ?>">Preview</a>
									<a target="_blank" href="<?= eval('?>'.$modeladmin->previewUrl) ?>"><img style="vertical-align: top;" src="<?= $admin->mediaRoot ?>/images/external-small.png"/></a>
								</td>
							<? } ?>
						</tr>
						<?
						if ($modeladmin->sortable && $canedit) { ?>
							</table></li>
						<? }
						$row = ($row == 'odd' ? 'even' : 'odd');
					}
				if ($modeladmin->sortable && $canedit) { ?>
				</ul>
				<? } else { ?>
				</table>
				<? } ?>
			<? } else { ?>
				No <?= htmlentities(strtolower($modeladmin->pluralName)) ?> found.<br />
			<? } ?>
			<br />
			<? if ($modeladmin->paginated) { ?>
			<div class="pagebuttons">
				<? if ($page > 1) { ?>
					<span class="prevpage"><a href="<?= $this->selfurl('_p', $page-1) ?>">Previous</a></span>
				<? } else { ?>
					<span class="prevpage disabled">Previous</span>
				<? } ?>
				-
				<? if ($page < $pages) { ?>
					<span class="nextpage"><a href="<?= $this->selfurl('_p', $page+1) ?>">Next</a></span>
				<? } else { ?>
					<span class="nextpage disabled">Next</span>
				<? } ?>
			</div>
			<? }
			if ($modeladmin->checkPermission('CREATE')) { ?>
				<span class="nextpage"><a href="<?= $sectionurl ?>/add/?<?= $filterstr ?>">Add new <?= htmlentities(strtolower($modeladmin->name)) ?></a></span><br />
				<br />
				<? if ($modeladmin->allowImport) { ?>
				<hr />
				<div class="expander">
					<div>
						<b>Import <?= htmlentities(strtolower($modeladmin->pluralName)) ?> from <acronym title="Comma Separated Values file format">CSV</acronym></b><br />
						<hr />
					</div>
					<div>
						<p>To import an existing set of <?= htmlentities(strtolower($modeladmin->pluralName)) ?> that are
						stored in a spreadsheet, save the spreadsheet in CSV format and upload
						it below.</p>
						<form action="<?= $sectionurl ?>/verifyimport/" method="post" enctype="multipart/form-data">
							<?= $this->selfform() ?>
							<table>
								<tr>
									<td>CSV file:</td>
									<td><input type="file" name="upload" /></td>
								</tr>
								<tr>
									<td>&nbsp;</td>
									<td><input type="checkbox" name="hasheader" value="1" checked /> Contains field heading row</td>
								</tr>
								<tr>
									<td>&nbsp;</td>
									<td><input type="submit" value="Review Import Data" /></td>
								</tr>
							</table>
						</form>
					</div>
				</div>
				<? }
			} ?>
		</td>
		<td class="filters">
			<div class="title">
				<span style="float: right; font-weight: normal;">
					[<a href="<?= $sectionurl ?>/<? if ($sort) echo '?_s='.$sort; ?>">clear all</a>]
				</span>
				Search filters
			</div>
			<hr/>
			<div class="content">
				<? if (count($searchFields)) { ?>
					<form action="" method="get">
						<b>Keyword</b><br />
						&nbsp;&nbsp;&nbsp;
						<? if ($search) { ?>
							<a href="<?= $this->selfurl('_q'); ?>"><img src="<?= $admin->mediaRoot ?>/images/red_delete.gif" /></a>	
						<? } ?>
						<input class="autocomplete" id="autocomplete_<?= $moduleid ?>_<?= $modeladmin->id ?>" type="text" name="_q" value="<?= $search ?>"/>
						<input type="submit" value="Filter"/>
						<?= $this->selfform('_q') ?>
					</form>
					<br />
				<? }

				foreach ($availfilters as $field => $filter) {
					if (count($filter['objects'])) { ?>
						<b><?= htmlentities($filter['name']) ?></b>
						<? if (count($filter['objects']) > 20) { ?>
							<br />
							&nbsp;&nbsp;&nbsp;
							<select size="1" onchange="window.location='<?= $this->selfurl($field, null, true) ?>'+(this.selectedIndex > 0 ? '<?= $field ?>='+(this.options[this.selectedIndex].value||0) : '');">
								<option <? if ($filter['empty']) echo 'selected'; ?> value="">All</option>
								<? foreach ($filter['objects'] as $fo) { ?>
									<option value="<?= $fo[0] ?>" <? if ($fo[2]) echo 'selected'; ?>><?= htmlentities($fo[1]) ?></option>
								<? } ?>
							</select>
							<br />
						<? } else { ?>
							<ul class="searchFilter">
								<? if ($filter['empty']) { ?>
									<li class="active">All</li>
								<? } else { ?>
									<li><a href="<?= $this->selfurl($field, null) ?>">All</a></li>
								<? }
								foreach ($filter['objects'] as $fo) {
									if ($fo[2]) { ?>
										<li class="active"><?= $fo[1] ?></li>
									<? } else { ?>
										<li><a href="<?= $this->selfurl($field, $fo[0]) ?>"><?= htmlentities($fo[1]) ?></a></li>
									<? }
								} ?>
							</ul>
						<? } ?>
						<br />
					<? }
				}
				if ($modeladmin->allowExport) { ?>
				<hr/>
				<b>Export Search Results</b>
				<hr/>
				<form action="<?= $sectionurl ?>/export/" method="post">
					<?= $this->selfform() ?>
					Format:
					<select name="exporttype" size="1">
						<option value="csv">CSV</option>
						<option value="xml">XML</option>
					</select>
					<input type="submit" value="Export" />
				</form>
				<? } ?>
			</div>
		</td>
	</tr>
</table>
