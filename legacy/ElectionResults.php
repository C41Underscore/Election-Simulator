<?php session_start(); ?><!--A session is opened so data from other files can be obtained via the superglobal-->
<html>
    <head>
      <title>Electoral Simulator</title>
      <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script><!--Connection to google charts library via API-->
      <link rel="stylesheet" href="ElectoralSimulatorStyle.css"><!--Connection to the style sheet-->
      <link href="https://fonts.googleapis.com/css?family=Prompt" rel="stylesheet">
      <link href="https://fonts.googleapis.com/css?family=Aleo" rel="stylesheet"><!--Connection to google fonts to get the required fonts for styling-->
    </head>

    <body>
      <h1 class="headline">Election results</h1>
      <h3 class="resultText"><span id="electionResult"></span><h3>
      <h3 class="resultText"><span id="electionTurnout"></span><h3><!--This shows the general result of the election, basically an overview.-->
      <div id="seatGraph" style="display: inline-block;"></div><!--Placeholder for the seats pie chart-->
      <div id="voteGraph" style="display: inline-block;"></div><!--Placeholder for the votes pie chart-->
      <div id="partyResults"></div><!--This is the parent node for all the party results when they are added on page load.-->
      <?php
        include("ElectoralSimulatorClassLibrary.php");//This allows the classes in this file to be used in this script.
        $election = new Election($_POST["electionChoice"], $_POST["modifierString"]);//A new election is instantiated, where the posted values from HomePage.php are sent into the superglobal $_POST, which contain the election year and the modifiers to be applied.
        $electionResult = $election->electionToJSON();//A fully simulated election is encoded in JSON and returned to a local variable.
        $_SESSION["election"] = $electionResult;//The encoded election is stored in a session variable.
      ?>
      <script>
        var electionJSON = String('<?php echo $_SESSION["election"]; ?>');//The session variable holding the election JSON string is outputted through PHP tags into a JavaScript variable.
        electionJSON.replace(/\s/g, '');//Special characters are removed from the JSON string.
        electionJSON = JSON.parse(electionJSON);//The encoded election string is parsed into a JavaScript object.
        var newResult;//Holds the P element to store the result in.
        var newText;//Holds the textnode to create the text.
        var newSpan;//Created to make styling much nicer - not required but it looks much better with minimal extra effort.
        if(electionJSON.parties[0].seatsWon >= 326)//This is checking if any party has won a majority in the election.
        {
          document.getElementById("electionResult").innerHTML = electionJSON.parties[0].partyName + " has won the election! <br> The new Prime Minister is " + electionJSON.parties[0].partyLeader + "!";//If a party has won the election, this is stated and the new
          //prime minister is also stated.
        } else
        {
          document.getElementById("electionResult").innerHTML = "It is a hung parliament!";//If no party has won at least 326 seats, then it's a hung parliament and this is stated.
        }
        document.getElementById("electionTurnout").innerHTML = electionJSON.electionStats["TurnoutInt"] + " people voted in this election which was a turnout of " + electionJSON.electionStats["TurnoutPercent"] + "%";//Both turnouts of the election are also given.
        for(party in electionJSON.parties)
        {
          if(electionJSON.parties[party].seatsWon > 0)//A party only has it's results outputted if it has won seats.
          {
            newResult = document.createElement("p");//The p element is created to store the text.
            newSpan = document.createElement("SPAN");//A span element is created to improve the styling.
            newText = document.createTextNode(electionJSON.parties[party].partyName + " has won " + electionJSON.parties[party].seatsWon + " seats and got " + electionJSON.parties[party].votesWon + " votes.");//A parametised statement is created.  It states the name
            //of the party, the number of seats it got and the number of votes.
            newSpan.appendChild(newText);//The text is made a child of the span.
            newResult.appendChild(newSpan);//The span is then made a child of the p element.
            newResult.className = "resultText";//It's given a class name which allows for consistant styling among all the elements among the results.
            document.getElementById("partyResults").appendChild(newResult);//The party result it added as a child of the results div.
          }
        }
        google.charts.load('current', {packages: ['corechart']});//This calls the appropriate charting functions and packages needed to present data.
        var graphOptions = {//These are the options to visually customize the pie charts
          width: 600,//This sets the width of the graph to 600 pixels
          height: 300//This sets the height of the graph to 300 pixels
        };

        window.onunload = function(){
      		<?php session_destroy(); ?>//If the window is reloaded, the session is detroyed.
      	};

        function drawSeatChart()
        {
          var dataTable = new google.visualization.DataTable();//This is a data table object, it holds the data in table form.
          dataTable.addColumn("string", "Party");//This adds a column called 'Party' which contains items of data type string.
          dataTable.addColumn("number", "Seats");//This adds a column called 'Seats' which contains items of data type integer (or number for google charts).
          var tempArray = Array();//This is used to be able to insert all the data into the table at once.
          var otherSeats = 0;
          for(party in electionJSON.parties)
          {
            if(electionJSON.parties[party].seatsWon > 0 && electionJSON.parties[party].isMain)//Only parties which have seats and are main parties get their own slice of the pie chart.
            {
              tempArray.push([electionJSON.parties[party].partyName, electionJSON.parties[party].seatsWon]);//An array is pushed to the add of the array which contains the party name and then the amount of seats the party won.
            } else if(electionJSON.parties[party].seatsWon > 0)//The party did win a seat but are not considered main.
            {
              otherSeats += electionJSON.parties[party].seatsWon;//If a party is not main, yet won a seat, then on the graph it's clumped with other non-main parties which are considered 'other' parties.
            }
          }
          tempArray.push(["Others", otherSeats]);//Other seats are pushed to the array.
          dataTable.addRows(tempArray);//All the data is added to the data tbale at once.
          var chart = new google.visualization.PieChart(document.getElementById("seatGraph"));//A new pie chart object is created, and it's placed in the seatGraph div.
          graphOptions.title =  "Seats won";//The graph header.
          chart.draw(dataTable, graphOptions);//The graph is drawn with the data from the data table, and the graph customisation options.
        }

        function drawVoteChart()
        {
          var dataTable = new google.visualization.DataTable();
          dataTable.addColumn("string", "Party");
          dataTable.addColumn("number", "Votes");
          var tempArray = Array();
          var otherVotes = 0;
          for(party in electionJSON.parties)
          {
            if(electionJSON.parties[party].isMain == true)//The only requirement for a party to have it's own slice is for it to be main.
            {
              tempArray.push([electionJSON.parties[party].partyName, electionJSON.parties[party].votesWon]);//An array containing the party name and the amount of votes it won is pushed to the array.
            } else {
              otherVotes += electionJSON.parties[party].votesWon;//Other votes are votes won by partiws not considered to be main.
            }
          }
          tempArray.push(["Others", otherVotes]);//The other votes have their own slice.
          dataTable.addRows(tempArray);
          var chart = new google.visualization.PieChart(document.getElementById("voteGraph"));//The votes graph has the voteGraph div as a placeholder.
          graphOptions.title =  "Votes won";
          chart.draw(dataTable, graphOptions);
        }

        google.charts.setOnLoadCallback(drawSeatChart);
        google.charts.setOnLoadCallback(drawVoteChart);//These are callback funcitons which uses the functions defined here to draw the graph.

        function binarySearch(item, start, end, searchArray)//This is a binary search which is used to find constituency results.
        {
          if((searchArray.length == 1 && searchArray[0].nametoUpperCase() != item) || start > end)//The base case - if the array length is 1 and the last item in the array is not equal to the find trying to be found.
          //Also if the start point has become larger than the end point.
          {
            return null;//No value was found, so null is returned.
          } else if(searchArray.length == 2)//If the array length is 2, both elements should be checked.
          {
            if(searchArray[0].name.toUpperCase() == item)
            {
              return searchArray[0];
            } else if(searchArray[1].name.toUpperCase() == item)
            {
              return searchArray[1];
            }
          }//Each conditional checks for the first then second element of the array respectively.
          var mid = Math.floor((end + start)/2);//The mid pointer is found.
          if(searchArray[mid].name.toUpperCase() == item)//The middle value of the array is checked using the mid point and compared against the item to find.
          {
            var returnResult = mid;
            return returnResult;//The value of the mid pointer is then returned.
          } else if(searchArray[mid].name.toUpperCase() < item)//If the name was 'smaller', it comes before it alphabetically, tbe first half of the array is recursively passed into the binarySearch function.
          {
            return binarySearch(item, mid + 1, end, searchArray);
          } else//If the name was 'bigger', it comes after it alphabetically, tbe second half of the array is recursively passed into the binarySearch function.
          {
            return binarySearch(item, start, mid - 1, searchArray);
          }
        }

        function searchConstituencyResults(constituency)//The constituency parameter is passed from a HTML input box.
        {
          var constituencyResult = null;
          var conValidation = new RegExp("([a-zA-Z,-‘]+)");//This is the regular expression use to validate the constituency input.
          if(conValidation.test(constituency))//THis is a method to match the regex string to the constituency name input.
          {
            for(region in electionJSON.regions)//Constituencies are stored in regions, therefore the regions must be iterated through.
            {
              constituencyResult = binarySearch(constituency.toUpperCase(), 0, electionJSON.regions[region].constituencies.length - 1, electionJSON.regions[region].constituencies);//A binary search is called with the constituency name and the regional constituencies.
              if(constituencyResult != null)
              {
                constituencyResult = electionJSON.regions[region].constituencies[constituencyResult];//The pointer returned from the binary search is used to find the constituency object and it's properties.
                var resultElement;
                var resultTextNode;
                var elementArray = Array(["h3", constituencyResult.name], ["p", "Electorate: " + constituencyResult.electorate], ["p", "Turnout in Voters: " + constituencyResult.turnoutNumber], ["p", "Turnout: " + (constituencyResult.turnoutPercentage * 100) + "%"]);
                //This contains an array of each item of data from the constituency which needs to be displayed, and the HTML element it should be presented in.
                while (document.getElementById("constituencyResult").hasChildNodes())//If there is currently constituency data already present as a result of another search, this will return true.
                {
                  document.getElementById("constituencyResult").removeChild(document.getElementById("constituencyResult").lastChild);//The last child element of the results div is removed.  This triggers for every child node there is.
                }
                for(i = 0; i < elementArray.length; i++)
                {
                  resultElement = document.createElement(elementArray[i][0]);//This takes the HTML element from i in the array.
                  resultTextNode = document.createTextNode(elementArray[i][1]);//This takes the actual text which should be presented.
                  resultElement.appendChild(resultTextNode);//The text node is added to the HTML element.
                  document.getElementById("constituencyResult").appendChild(resultElement);//The data is appended to the bottom of the div.
                }
                resultElement = document.createElement("h5");
                resultTextNode = document.createTextNode("Candidates");
                resultElement.appendChild(resultTextNode);
                document.getElementById("constituencyResult").appendChild(resultElement);
                for(i = 0; i < constituencyResult.candidates.length; i++)//Each candidate in the constituency is iterated through so that it's details can be presented.
                {
                  resultElement = document.createElement("p");
                  resultTextNode = document.createTextNode(constituencyResult.candidates[i].fornames + " " + constituencyResult.candidates[i].surname + " got " + constituencyResult.candidates[i].votes + " votes for " + constituencyResult.candidates[i].party);
                  //parametised statement which will show the names of each candidate, how many votes they got and for which party.
                  resultElement.appendChild(resultTextNode);
                  document.getElementById("constituencyResult").appendChild(resultElement);//The candidate details are added to the page.
                }
              }
            }
          }
        }
      </script>
      <input type="text" id="constituencySearch" placeholder="Search constituency"/><!--This is the constituency search input-->
      <button type="button" name="searchSubmit" onclick="searchConstituencyResults(document.getElementById('constituencySearch').value);">Search</button><!--This triggers the constituency search-->
      <div id="constituencyResult"></div><!--This is where the constituency search results are placed.-->
      <?php
      try {//A try catch is used because the session variable might not exist.
        $conn = new mysqli("localhost", "177548", "B4H47lGCcwGeNdIC", "simulatordb");//A connection to the simulator database is established.
        $currentUser = $_SESSION["currentUser"];//The current user is found
        $currentElectionsDone = $conn->query("SELECT DISTINCT `electionsSimulated` FROM `studentcredentials` WHERE `studentID` = '".$currentUser."';");//THe amount of elections the students have simulated is searched.
        $currentElectionsDone = $currentElectionsDone->fetch_Assoc();//The actual results are pulled.
        $currentElectionsDone = ((int)$currentElectionsDone["electionsSimulated"]) + 1;//The number of elections this user has simulated is incredmented by 1, as they have now simulated 1 more election.
        $conn->query("UPDATE `studentcredentials` SET `electionsSimulated` = '".$currentElectionsDone."' WHERE `studentID` = '".$currentUser."';");//The new number of elections the user has simulated is set in the database.
      } catch (\Exception $e) {

      }
      ?>
    </body>
  </html>
