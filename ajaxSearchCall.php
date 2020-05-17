<?php
$conn = new mysqli("localhost", "177548", "B4H47lGCcwGeNdIC", "simulatordb");//A connection to the database is established.
$conn->set_charset("utf-8");//The character set is set to utf-8 to prevent empty and unrecognised characters.
$search;
if($_GET["callType"] == "Individual Constituency")//The call type refers to whether the user is searching for a region or a constituency.
{
  $search = $conn->query("SELECT `Name` FROM `regions` ORDER BY `Name` ASC");//All of the constituencies are pulled from the database.
} elseif($_GET["callType"] == "Region")
{
  $search = $conn->query("SELECT DISTINCT `Region` FROM `regions` ORDER BY `Name` ASC");//All of the unique occurances of the region names are pulled from the database.
}
$returnString = "";
while($nextVal = $search->fetch_assoc())//This while loop will iterate whilst $search->fetch_assoc() does not return false.
{
  if($nextVal == false)
  {
    break;
  } else
  {
    if($_GET["callType"] == "Individual Constituency")//For constituency searchs.
    {
      if(strtolower(substr($nextVal["Name"], 0, strlen($_GET["currentSearch"]))) == strtolower($_GET["currentSearch"]))//The current search is compared to the equal amount of characters in each constituency result.  This is true if those characters match exactly.
      {
        if($returnString == "")//Is this the first name being put into the return result?
        {
          $returnString = $nextVal["Name"];//Return result is set to the constituency name.
        } else

          $returnString = $returnString.", ".$nextVal["Name"];//If it is not the first result, then the values must be seperated, so a comma is used and the constituency name happens.
        }
      } elseif($_GET["callType"] == "Region")
      {//The same happens here as it does in the constituency search.
        if(strtolower(substr($nextVal["Region"], 0, strlen($_GET["currentSearch"]))) == strtolower($_GET["currentSearch"]))
        {
          if($returnString == "")
          {
            $returnString = $nextVal["Region"];
          } else
          {
            $returnString = $returnString.", ".$nextVal["Region"];
          }
      }
    }
  }
}
echo $returnString;//Echo is used to output the string of suggested searches.
exit;//The script is terminated.
?>
