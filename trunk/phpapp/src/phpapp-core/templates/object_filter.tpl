<h1><?= htmlentities(ucwords($modeladmin->pluralName)) ?></h1>

<? if (count($filterFields) || count($searchFields)) { ?>
<table cellspacing="0" cellpadding="0" class="filterTable">
	<tr>
		<td class="list">
<? }
if ($objectcount) { ?>
	<div class="pagelist">
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
		Viewing <?= $pagestart ?> - <?= $pageend ?> of <?= $objectcount ?>
	</div>
	<table cellspacing="0" cellpadding="0" class="listTable">
		<tr>
		<?	if (!count($displayFields)) { ?>
				<th>Name</th>
			<? } else {
				foreach ($displayFields as $field) {
					$fieldobj = $modeladmin->findField($field);
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
							<?= htmlentities($fieldobj->name) ?>
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
			} ?>
		</tr>
		<?
		$row = 'odd';
		foreach ($objects as $object) { ?>
			<tr class="<?= $row ?>">
				<?
				if (!count($displayFields)) { ?>
					<td><a href="<?= $sectionurl ?>/filter/<?= $object->pk ?>"><?= htmlentities($object) ?></a></td>
				<? } else {
					$first = true;
					foreach ($displayFields as $field) {
						$fieldobj = $modeladmin->findField($field); ?>
							<td>
								<? if ($first && ($fieldobj->field != $object->_idField || count($displayFields) == 1)) {
									$first = false; ?>
									<a href="<?= $sectionurl ?>/filter/<?= $object->pk ?>"><?= $modeladmin->getFieldValueHTML($object, $field, 'text') ?></a>
								<? } else { ?>
									<?= $modeladmin->getFieldValueHTML($object, $field) ?>
								<? } ?>
							</td>
					<? }
				} ?>
			</tr>
			<?
			$row = ($row == 'odd' ? 'even' : 'odd');
		} ?>
	</table>
<? } else { ?>
	No <?= htmlentities(strtolower($modeladmin->pluralName)) ?> found.<br />
<? } ?>
<br />
<? if (count($filterFields) || count($searchFields)) { ?>
		</td>
		<td class="filters">
			<div class="title">
				<span style="float: right; font-weight: normal;">
					[<a href="<?= $sectionurl ?>/filter/<? if ($sort) echo '?_s='.$sort; ?>">clear all</a>]
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
							<a href="<?= $this->selfurl('_q') ?>"><img src="<?= $admin->mediaRoot ?>/images/red_delete.gif" /></a>	
						<? } ?>
						<input class="autocomplete" id="autocomplete_<?= $moduleid ?>_<?= $modeladmin->id ?>" type="text" name="_q" value="<?= $search ?>"/>
						<input type="submit" value="Filter"/>
						<?= $this->selfform('_q') ?>
					</form>
					<br />
				<? }

				foreach ($availfilters as $field => $filter) {
					if (count($filter['objects'])) { ?>
						<b><?= $filter['name'] ?></b>
						<? if (count($filter['objects']) > 10) { ?>
							<br />
							&nbsp;&nbsp;&nbsp;
							<select size="1" onchange="window.location='<?= $this->selfurl($field, null, true) ?>'+(this.selectedIndex > 0 ? '<?= $field ?>='+(this.options[this.selectedIndex].value||0) : '');">
								<option <? if ($filter['empty']) echo 'selected'; ?> value="">All</option>
								<? foreach ($filter['objects'] as $fo) { ?>
									<option value="<?= $fo[0] ?>" <? if ($fo[2]) echo 'selected'; ?>><?= $fo[1] ?></option>
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
										<li><a href="<?= $this->selfurl($field, $fo[0]) ?>"><?= $fo[1] ?></a></li>
									<? } 
								} ?>
							</ul>
						<? } ?>
						<br />
					<? }
				} ?>
			</div>
		</td>
	</tr>
</table>
<? } ?>
