<?php session_start() ?>
<html>
	<head>
		<title>Electoral simulator</title>
		-<link rel="stylesheet" href="ElectoralSimulatorStyle.css" type="text/css"/><!--Link to the stylesheet.-->
    <link href="https://fonts.googleapis.com/css?family=Prompt" rel="stylesheet">
		<link href="https://fonts.googleapis.com/css?family=Aleo" rel="stylesheet"><!--Links to the required fonts-->
	</head>
	<body>
		<h1>Electoral Simulator</h1>
		<form action="checkCredientials.php" method="post"><!--Form data is posted to a PHP script in order to be validated.-->
			<legend>Log In:</legend>
			<br>
			Username: <input name="UserID" type="text" placeholder="Username">
			Password: <input name="Password" type="password" placeholder="Password">
			<br>
			<br>
			<input type="submit" value="Log in">
		</form>
		<p id="response" style="color: red;"></p><!--This element is only visible if the user enters invalid credientials-->
		<script>
		try {//it's in a try catch because the invalid session variable may not exist, if that's the case nothing needs to happen.
			var afterLogInAttempt = "<?php echo $_SESSION["invalid"] ?>"//Checks to see if an invalid login attempt has been made.
			if(afterLogInAttempt == "1")//An invalid login attempt has been made.
			{
				document.getElementById("response").innerHTML = "Invalid log in credientials";//The user is notified that their credientials were invalid.
			}
		} catch (e) {

		}
		</script>
	</body>
	<script>
	window.onbeforeunload = function(){
		<?php session_destroy(); ?>//If the window is reloaded, the session is detroyed.
	};
	</script>
</html>
