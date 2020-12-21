
<?php
session_start();//The session is opened so this script can interact with the wider program.
$conn = new mysqli("localhost", "177548", "B4H47lGCcwGeNdIC", "simulatordb");
$username = $_POST["UserID"];
$password = $_POST["Password"];//Posted data from form being stored in local variables.
$check = $conn->query("SELECT `studentID`, `studentPassword` FROM `studentcredentials` WHERE EXISTS (SELECT DISTINCT `studentID`, `studentPassword` FROM `studentcredentials` WHERE `studentID` = '".$username."' &&
   `studentPassword` = '".$password."');");//This query is checking to see if a user with the entered log in credientials exists.
if($check->num_rows > 0)//If num_rows is bigger than 0, it means that a match with the credientials has been found.
{
  $_SESSION["currentUser"] = $username;
  $timestamp = date("Y-m-d G:i:s");//A date object is being created with the current time of log in, to log the time this user logged in.
  $conn->query("UPDATE `studentcredentials` SET `lastTimeLoggedIn` = '".$timestamp."' WHERE `studentID` = '".$username."';");//Students last log in date is being recorded and entered into the database.
  $teacherCheck = $conn->query("SELECT `studentID`, `studentPassword`, `isTeacher` FROM `studentcredentials` WHERE EXISTS (SELECT DISTINCT `studentID`, `studentPassword`, `isTeacher` FROM `studentcredentials`
    WHERE `studentID` = '".$username."' &&
     `studentPassword` = '".$password."' && `isTeacher` = 1);");//The user's credientials are being checked to see if they are a teacher or not.
  if($teacherCheck->num_rows > 0)//If there is a teacher, num_rows will be greater than 1.
  {
    header('Location: TeacherAdminPage.php');//The teacher is taken to the teacher admin page.
    exit;
  }
  header('Location: HomePage.php');//If the user is not a teacher, they are taken straight to the homepage.
  exit;
} else {
  header('Location: index.php');//If num_rows is 0, they user credientials are invalid and the user is taken back to the log page.
  $_SESSION["invalid"] = true;
  exit;
}
?>
