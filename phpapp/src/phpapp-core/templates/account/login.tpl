<h1>Login to <?= $admin->appName ?></h1>

<? if (isset($error)) { ?>
	<div class="error"><?= $error ?></div>
	<br />
<? } ?>

<form action="login" method="POST" class="focusonload">
	<input type="hidden" name="redirect" value="<?= $redirect ?>"/>
	<table cellpadding="0" cellspacing="0" class="loginTable">
		<tr>
			<td class="editLabel">Username</td>
			<td class="editField"><input type="text" name="username"/></td>
		</tr>
		<tr>
			<td class="editLabel">Password</td>
			<td class="editField"><input type="password" name="password"/></td>
		</tr>
		<tr>
			<td class="editLabel">Auto-login</td>
			<td class="editField"><input type="checkbox" name="remember" value="true"/></td>
		</tr>
	</table>
	<br />
	<input type="submit" value="Login"/>
</form>
