<h1><?= htmlentities($title) ?></h1>

<p><?= htmlentities($error) ?></p>

<? if (isset($exception) && isset($app->config['debug']) && $app->config['debug']) {
	echo '<div class="debug">';
	echo '<hr />';
	echo '<b>Debug Info</b>';
	echo '<hr />';
	echo '<p>Source: '.$exception->getFile().':'.$exception->getLine().'</p>';
	echo '<p style="white-space: pre-wrap">';
	echo "Backtrace:\n";
	echo $exception->getTraceAsString();
	echo '</p>';
	echo '</div>';
} ?>
