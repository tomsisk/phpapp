<h1>My Account</h1>

<? if ($errors or $fieldErrors) { ?>
	<div class="error">
		Please correct the errors listed below and submit again:
		<? if ($errors) { ?>
			<ul>
				<? foreach ($errors as $error) { ?>
					<li><?= $error ?></li>
				<? } ?>
			</ul>
		<? } ?>
	</div>
	<br />
<? } ?>

<form action="save" method="POST" class="focusonload">
	<table width="710" cellspacing="0" cellpadding="0" border="0" class="editTable">
		<tr>
			<td class="editLabel">Email<span class="required">*</span></td>
			<td class="editField"><input type="text" class="editInput" name="email" value="<?= $user->email ?>" style="width:300px"/></td>
		</tr>
		<tr>
			<td class="editLabel">New Password</td>
			<td class="editField"><input type="password" class="editInput" name="password1" style="width:300px"/></td>
		</tr>
		<tr>
			<td class="editLabel">Confirm Password</td>
			<td class="editField"><input type="password" class="editInput" name="password2" style="width:300px"/></td>
		</tr>
		<tr>
			<td class="editLabel">First Name</td>
			<td class="editField"><input type="text" class="editInput" name="firstname" value="<?= $user->firstname ?>" style="width:300px"/></td>
		</tr>
		<tr>
			<td class="editLabel">Last Name</td>
			<td class="editField"><input type="text" class="editInput" name="lastname" value="<?= $user->lastname ?>" style="width:300px"/></td>
		</tr>
	</table>
	<br />
	<div style="text-align:center;">
		<input type="submit" value="Update Account"/>
	</div>
</form>
