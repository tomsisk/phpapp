<h1><?= htmlentities($title) ?></h1>

<p><?= htmlentities($error) ?></p>

<? if (isset($exception)) { 
	if ($exception instanceof ValidationException) {
		$m = $exception->getModel();
		echo '<div class="debug">';
		echo '<hr />';
		echo '<b>Error Details</b>';
		echo '<hr />';
		echo '<p>Occurred in: "'.strval($m).'" <i>(id='.($m->pk?$m->pk:'none').')</i></p>';
		echo '<ul>';
		if ($exception->getErrors())
			foreach ($exception->getErrors() as $e)
				echo '<li>'.$e.'</li>';
		if ($exception->getFieldErrors()) {
			foreach ($exception->getFieldErrors() as $f => $el) {
				foreach ($el as $e)
					echo '<li>'.$f.': '.$e.'</li>';
			}
		}
		echo '</ul>';
		echo '</div>';
	}
	if (isset($app->config['debug']) && $app->config['debug']) {
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
	}
} ?>
