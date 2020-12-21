<html>
<head>
  <title>Electoral Simulator</title>
  <link rel="stylesheet" href="ElectoralSimulatorStyle.css" type="text/css"/><!--Link to stylesheet-->
  <link href="https://fonts.googleapis.com/css?family=Prompt" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css?family=Aleo" rel="stylesheet"><!--Links to required fonts-->
</head>

<body>
  <h1>Teacher administration</h1>
  <?php
  $conn = new mysqli("localhost", "177548", "B4H47lGCcwGeNdIC", "simulatordb");
  $studentDetails = $conn->query("SELECT DISTINCT `studentID`, `Surname`, `Forenames`, `electionsSimulated`, `lastTimeLoggedIn` FROM `studentcredentials` WHERE `isTeacher` = 0;");//Finding all students in the database.
  echo "<table style='display: inline-block;'><tr><th>Student ID</th><th>Surname</th><th>Forenames</th><th>Elections simulated</th><th>Last Log In</th></tr>";//Creating the headers of the table to display the student data.
  while($student = $studentDetails->fetch_Assoc())//Fetch the details of the next student.
  {
    echo "<tr><td>".$student["studentID"]."</td><td>".$student["Surname"]."</td><td>".$student["Forenames"]."</td><td>".
    $student["electionsSimulated"]."</td><td>".$student["lastTimeLoggedIn"]."</td></tr>";//A row of data being appended to the table with the student's details.
  }
  echo "</table>";//The table being closed off.
  ?>
  <br>
  <br>
  <a href="HomePage.php" target="_blank"><button type="button">To the simulator</button></a><!--A link to take the teacher to the simulator homepage.  The homepage will open on a seperate tab to allow the teacher to view stats.-->
</body>
</html>
