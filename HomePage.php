<?php session_start();
try {
	if(!(isset($_SESSION["currentUser"])))//If a current user does not exist, it will take the user to the log in page to log in, this is an extra layer of security.
	{
		header('Location: index.php');
	  $_SESSION["invalid"] = true;//This will flag that the user was invalid, the invalid user message will appear on the log in page.
	  exit;//This entire script will now cancel.
	}
} catch (\Exception $e)
{//This will trigger is currentUser doesn't exist, this also means the user isn't verified.
	header('Location: index.php');
  $_SESSION["invalid"] = true;
  exit;
} ?>
<html>
	<head>
		<title>Electoral Simulator</title>
		<link rel="stylesheet" href="ElectoralSimulatorStyle.css" type="text/css"/><!--Connection to the stylesheet-->
    <link href="https://fonts.googleapis.com/css?family=Prompt" rel="stylesheet">
		<link href="https://fonts.googleapis.com/css?family=Aleo" rel="stylesheet"><!--The required fonts for the styling-->
	</head>

	<body>
		<h1 class="headline">Electoral Simulator</h1>
		<p id="searchP">Search suggestions: <span id="searchResult"></span></p><!--The search suggestions for constituencies and regions appear in this element-->
		<form method="post" action="ElectionResults.php" id="simulatorForm"><!--This form will post the data from this form to ElectionResults.php-->
			<legend>Choose an election to simulate:</legend>
			<select name="electionChoice">
				<option value="2010">2010</option>
				<option value="2015">2015</option>
				<option value="2017">2017</option>
			</select><!--These are the elections that the user gets to choose from, they select one from a drop-down list-->
			<br>
      <br>
			<input type="submit" value="Simulate Election!" onclick="document.getElementById('modifierString').value = getJSON();"/><!--This will trigger the call to ElectionResults.php,
				and will also call a function which the value of the hidden element modifierString to the JSON string with the modifiers.-->
      <br>
			<br>
			<div id="modifiers">
				<p id="modifierCount">Number of current modifiers: 0</p>
				<p>New modifier:</p>
				<select id="modifierChoice">
					<option value="Age">Age</option>
					<option value="Ethnicity">Ethnicity</option>
					<option value="Class">Class</option>
					<option value="Partisan Dealignment">Partisan dealignment</option>
					<option value="Voting System">Alternate Voting system</option>
					<option value="Turnout">Turnout</option><!--The user can choose the type of modifier that they want ot apply to the election.-->
				</select>
				<select id="newModifierRangeOfEffect">
					<option>Individual Constituency</option>
					<option>Region</option>
					<option>UK</option><!--The user can set the range of effect for their modifier using a select drop down list.-->
				</select>
      	<br>
      	<br>
				<button type="button" onclick="addNewModifier(document.getElementById('modifierChoice').value)">New Modifier</button>
				<p id="modifierBackstop"></p><!--This is a 'backstop', where the elements for the modifiers are inserted into the form before this element,
					it allows it to stay in the modifiers div.-->
				<input type="hidden" name="modifierString" id="modifierString"/><!--This stores the JSON modifier string which gets posted to the ElectionResults.php script-->
			</div>
		</form>
	</body>
	<script>
	class Modifier//A class which represents a modifier.
	{
	  constructor(type, id, rangeOfEffect, modDiv)
	  {
	    this.type = type;
	    this.id = id;
			this.modDiv = modDiv;//This stores the references to all of the HTML elements which are associated with this modifier, it allows the object to retrieve their values when the election is simulated.
			this.primaryEffect = null;
			this.secondaryEffect = null;
			this.tertiaryEffect = null;
	    this.rangeOfEffect = rangeOfEffect;
	    this.areaToEffect = null;//This sets up the initial details of the modifier.
	  }

	  setID(newID)
	  {
			for(var node in this.modDiv.childNodes)//For each element associated with with the modifier.
			{
				if(typeof this.modDiv.childNodes[node].id !== "undefined")//This checks for any modifiers where it's ID has been set.
				{
					this.modDiv.childNodes[node].id = this.modDiv.childNodes[node].id.substring(0, this.modDiv.childNodes[node].id.length - 1) + newID;//The ID is now a substring of the old ID, where it's taken the ID name and removed the old ID to add the new number.
				}
			}
			this.id = newID;
			document.getElementById("modifierTitle" + newID).innerHTML = "Modifier " + this.id + " - " + this.type;//The header for the modifier is updated to the modifier's new ID, for consistency and clarity for the user.
	  }

	  getID()
	  {
	    return this.id;
	  }

		removeModifier()
		{
			this.modDiv.remove();
		}

		setDetails()
		{
			for(var node in this.modDiv.childNodes)
			{
				if(typeof this.modDiv.childNodes[node].id !== "undefined" && this.modDiv.childNodes[node].id.substring(0, this.modDiv.childNodes[node].id.length - 1) == "rangeInput")//This is finding the text input from the user about the range of effect.
				{
					this.areaToEffect = this.modDiv.childNodes[node].value;//the value entered into the rnage input box is part of this object, with allows this input to be stringified.
				} else if(typeof this.modDiv.childNodes[node].className !== "undefined")//Check for all elements with defined classname.
				{
					switch(this.modDiv.childNodes[node].className)
					{
						case "primaryEffect":
							this.primaryEffect = this.modDiv.childNodes[node].value;
							break;
						case "secondaryEffect":
							this.secondaryEffect = this.modDiv.childNodes[node].value;
							break;
						case "tertiaryEffect":
							this.tertiaryEffect = this.modDiv.childNodes[node].value;
							break;
					}//Only 3 different classnames have been used in this program, and not all of them appear in every modifier, therefore a switch case has been used to fetch the data.
				}
			}
			delete this.modDiv;//The modDiv property is deleted.  This is because we do not want to parse the HTML to the Election object.  This property does not appear in the stringified object.
			delete this.id;//This id property is removed because it is not needed for the execution of the modifiers so doesn't need to be encoded.
		}
	}

	class ModifierCollection
	{
	  constructor()
	  {
	    this.modifierArray = Array("HEAD");//This shifts the modifierArray to index 1, aligning it with the modifier IDs.
	  }

	  addModifier(modifier)
	  {
	    this.modifierArray.push(modifier);//A new modifier object is added to the end of modifierArray.
	  }

	  removeModifier(modifierID)
	  {
	    modifierID = modifierID.substr(2);//Modifier ID is set to the 3rd character of the modifierID parameter, which is the unqiue number of it's the remove button.
			this.modifierArray[modifierID].removeModifier();//This causes the div the to-be removed modifier to be deleted.
	    this.modifierArray.splice(modifierID, 1);//The modifier is removed from the modifierArray.
	    this.modifierArray.forEach((modifier) => {
	      if(modifier != "HEAD" && this.modifierArray.length > 1)//This will only execute on modifiers and if there are current modifiers in the array.
	      {
	        if(modifier.getID() >= modifierID)
	        {
	          modifier.setID(modifier.getID() - 1);//For any modifiers which have an ID greater than the one which was removed, their modifier IDs need to be decremented by 1.
	        }
	      }
	    });
	    if(this.modifierArray.length - 1 == 0)//Altering the modifiers' div title.
	    {
	      document.getElementById("modifierCount").innerHTML = ("Number of current modifiers: 0");//If HEAD is the only element of the array, there are no modifiers, so the title can be hard coded.
	    } else {
	      document.getElementById("modifierCount").innerHTML = ("Number of current modifiers: " + ((this.modifierArray.length - 1) * 1));
	    }
	  }

	  getNumberOfMods()
	  {
	    return this.modifierArray.length - 1;
	  }

		setDetails()
		{
			for(var i = 1; i < this.modifierArray.length; i++)
			{
				this.modifierArray[i].setDetails();//Each modifier fetches data from their HTML elements and makes them properties, meaning they can be stringified with the modifierCollection.
			}
			this.modifierArray.splice(0, 1);
			return this;//Returns a JSON string containing all the modifier data.
		}
	}

	var modifierArray = new ModifierCollection;
	var newDiv = document.createElement("div");
	newDiv.id = "modifierDiv";
	var parentForm = document.getElementById("modifiers");
	var backstop = document.getElementById("modifierBackstop");
	parentForm.insertBefore(newDiv, backstop);

	function addNewModifier(modifierName)
	{

	  var modifierCount = modifierArray.getNumberOfMods();
	  modifierCount += 1;
	  document.getElementById("modifierCount").innerHTML = ("Number of current modifiers: " + modifierCount);//The title is updated to reflect how many modifiers there are.

	  var modDiv = document.createElement("div");
	  modDiv.id = "modifier" + modifierCount;
	  var modifierTitle = document.createElement("h3");
	  var textNode = document.createTextNode("Modifier " + modifierCount + " - " + document.getElementById("modifierChoice").value)//The modifier header is set to show which modifier it is.
	  with (modifierTitle)
	  {
	    id = "modifierTitle" + modifierCount;
	    appendChild(textNode);
	  }//Title element is set up.

	  var rangeOfEffect = document.createElement("h5");
		var rangeEnter;
	  if(document.getElementById("modifierChoice").value != "Voting System")//Voting system is the only modifier where the range of effect has to be the entire UK.
	  {
	    var rangeText = document.createTextNode("Range of Effect - " + document.getElementById("newModifierRangeOfEffect").value);
	  } else {
	    var rangeText = document.createTextNode("Range of Effect - UK");
	  }
	  with (rangeOfEffect)
	  {
	    id = "rangeOfEffect" + modifierCount;
	    appendChild(rangeText);
	  }//Range of effect title is set up.

	  if(document.getElementById("newModifierRangeOfEffect").value != "UK" && document.getElementById("modifierChoice").value != "Voting System")//No range of effect input needed if range of effect is UK.
	  {
	    var rangeInput = document.createElement("input");
	    with (rangeInput)
	    {
	      id = "rangeInput" + modifierCount;
	      type = "text";
	    }//Setting up the basic text input.
	    rangeInput.addEventListener("keyup", function()
			{
	      if(this.value != "")
	      {
	        serverRequest = new XMLHttpRequest();//AJAX being used to create a live search.
	        serverRequest.onreadystatechange = function()
					{
	          if(this.readyState == 4 && this.status == 200)//4 means response received and 200 is the HTTP code meaning the request was 'OK'.
	          {
	            document.getElementById("searchResult").innerHTML = this.responseText;//When a response is received from the script this is executed.
	          }
	        };
	        serverRequest.open('GET', "ajaxSearchCall.php?callType=" + document.getElementById("newModifierRangeOfEffect").value + "&currentSearch=" + this.value, true);//PARAMETER 1: HTTP transfer method.  PARAMETER 2: The URL and parameters being transferred(callType & currentSearch)
					//PARAMETER 3: true meaning the request is asynchronous.
	        serverRequest.send();//The request is sent and script is called.
	      } else
				{
	        document.getElementById("searchResult").innerHTML = "";//This triggers if the range input is empty.
	      }
	    });
	    if(document.getElementById("newModifierRangeOfEffect").value == "Constituency")
	    {
	      rangeInput.placeholder = "Constituency Name";
	    } else if(document.getElementById("newModifierRangeOfEffect").value == "Region")
			{
	      rangeInput.placeholder = "Region Name";
	    }//The placeholder is set dependning on which type of range is being searched for.
			rangeEnter = rangeInput;//This local variable is set to have entire function scope.
	  }

	  switch (modifierName)
	  {//a switch is being used select which modifier UI to create.  Only age is explained because all processes are similar just with slight variations.
	    case "Age":
	      var description = document.createElement("p");
	      textNode = document.createTextNode("Select an age group to increase or decrease in proportion");
	      description.appendChild(textNode);//A description of how to use the modifier.
	      var ageSelect = document.createElement("select");
				var ageOptions = Array("18-24",  "25-29", "30-44", "45-59", "60-64", "65+");//These are all the options that will be on the drop down list.
				ageSelect.className = "primaryEffect";
				var ageOption;
				for(var i = 0; i < ageOptions.length; i++)
				{
					ageOption = document.createElement("option");
		      ageOption.value = ageOptions[i];
		      ageOption.text = ageOptions[i];
		      ageSelect.add(ageOption);//Each option is set up from the ageOptions array.
				}
				var br = document.createElement("br");
	      var inputBox = document.createElement("input");
	      with (inputBox){
	        type = "text";
	        className = "secondaryEffect";
	      }//This is the input for proportion change.
				textNode = document.createElement("p");
	      extraText = document.createTextNode("^% increase/decrease in proportion.  For a decrease in proportion use a negative value.");
	      textNode.appendChild(extraText);//This is further description for how to use the modifier.
	      removeButton = document.createElement("button");
	      var buttonText = document.createTextNode("Remove modifier");
	      with (removeButton)
	      {
	        id = "rm" + modifierCount;
	        type = "button";
	        addEventListener("click", function() {
						modifierArray.removeModifier(this.id);//This will remove a modifier and all of it's HTML elements (the one that this function call is attached to).
	        });
	        appendChild(buttonText);
	      }//This is the removal button for modifiers.
	      with (modDiv)
	      {
	        appendChild(modifierTitle);
	        appendChild(rangeOfEffect);
				}//This appends the modifier title and range of effect description to the modifier div.
				if(document.getElementById("newModifierRangeOfEffect").value != "UK")
				{
					modDiv.appendChild(rangeEnter);
				}//The range of effect input box is only appended to the modifier div if the range of effect if not the UK.
				with (modDiv)
				{
	        appendChild(description);
					appendChild(ageSelect);
					appendChild(br);
					appendChild(inputBox);
	        appendChild(textNode);
	        appendChild(removeButton);
	      }//The rest of the elements are appended to the modifier div.
	      document.getElementById("modifierDiv").appendChild(modDiv);//The modifier div is appended to the modifier div containing all the modifiers.
	      break;
	    case "Ethnicity":
	      var description = document.createElement("p");
	      textNode = document.createTextNode("Select an ethnic group to change the proportion of:");
				description.appendChild(textNode);
	      var ethSelect = document.createElement("select");
	      var ethOption;
				var ethOptions = Array("White British", "BME", "Jewish", "Hindu", "Sikh", "Catholic", "Protestant");
				for(var i = 0; i < ethOptions.length; i++)
				{
					ethOption = document.createElement("option");
		      ethOption.value = ethOptions[i];
		      ethOption.text = ethOptions[i];
		      ethSelect.add(ethOption);
				}
	      ethSelect.className = "primaryEffect";
	      var inputDescript = document.createElement("p");
	      textNode = document.createTextNode("Enter a value between -100 and 100:");
	      inputDescript.appendChild(textNode);
	      var inputBox = document.createElement("input");
	      with (inputBox)
	      {
	        type = "text";
	        className = "secondaryEffect";
	      }
				var inputP = document.createElement("p");
				textNode = document.createTextNode("^% increase/decrease in.  For a decrease in proportion use a negative value.");
	      inputP.appendChild(textNode);
	      removeButton = document.createElement("button");
	      var buttonText = document.createTextNode("Remove modifier");
	      with (removeButton)
	      {
	        id = "rm" + modifierCount;
	        type = "button";
	        addEventListener("click", function() {
	          modifierArray.removeModifier(this.id);
	        });
	        appendChild(buttonText);
	      }
	      with (modDiv)
	      {
	        appendChild(modifierTitle);
	        appendChild(rangeOfEffect);
				}
				if(document.getElementById("newModifierRangeOfEffect").value != "UK")
				{
					modDiv.appendChild(rangeEnter);
				}
				with (modDiv)
				{
	        appendChild(description);
					appendChild(ethSelect);
	        appendChild(inputDescript);
					appendChild(inputBox);
	        appendChild(inputP);
	        appendChild(removeButton);
	      }
	      document.getElementById("modifierDiv").appendChild(modDiv);
	      break;
	    case "Class":
	      var description = document.createElement("p");
	      textNode = document.createTextNode("Choose the social class you would like to manipulate:");
	      description.appendChild(textNode);
	      var classSelect = document.createElement("select");
	      var classOption;
				var classOptions = Array("AB", "C1", "C2", "DE");
				for(var i = 0; i < classOptions.length; i++)
				{
					classOption = document.createElement("option");
		      classOption.value = classOptions[i];
		      classOption.text = classOptions[i];
		      classSelect.add(classOption);
				}
	      classSelect.className = "primaryEffect";
	      var inputP = document.createElement("p");
	      var inputText = document.createTextNode("Enter an integer value between -100 and 100.  This changes the proportion of the previous class chosen.");
				inputP.appendChild(inputText);
			  var inputBox = document.createElement("input");
	      with (inputBox)
	      {
	        type = "text";
	        className = "secondaryEffect";
	      }
	      var dealignmentInput = document.createElement("p");
	      var deInput = document.createTextNode("Class dealignment, enter a value between 1 and 100, if left blank, no effect will take place.");
	      dealignmentInput.appendChild(deInput);
	      deInput = document.createElement("input");
	      with (deInput)
	      {
	        type = "text";
	        className = "tertiaryEffect";
	      }
				var br = document.createElement("br");
	      removeButton = document.createElement("button");
	      var buttonText = document.createTextNode("Remove modifier");
	      with (removeButton)
	      {
	        id = "rm" + modifierCount;
	        type = "button";
	        addEventListener("click", function() {
	          modifierArray.removeModifier(this.id);
	        });
	        appendChild(buttonText);
	    	}
	      with (modDiv)
	      {
	        appendChild(modifierTitle);
	        appendChild(rangeOfEffect);
				}
				if(document.getElementById("newModifierRangeOfEffect").value != "UK")
				{
					modDiv.appendChild(rangeEnter);
				}
				with (modDiv)
				{
	        appendChild(description);
	        appendChild(classSelect);
	        appendChild(inputP);
					appendChild(inputBox);
	        appendChild(dealignmentInput);
					appendChild(deInput);
					appendChild(br);
	        appendChild(removeButton);
	      }
	      document.getElementById("modifierDiv").appendChild(modDiv);
	      break;
	    case "Partisan Dealignment":
	      var description = document.createElement("p");
	      textNode = document.createTextNode("Enter a number between 1 and 100, if left blank, not effect will take place.");
	      description.appendChild(textNode);
	      var inputBox = document.createElement("input");
	      with (inputBox)
	      {
	        type = "text";
	        className = "primaryEffect";
	      }
				var lineBreak = document.createElement("br");
	      removeButton = document.createElement("button");
	      var buttonText = document.createTextNode("Remove modifier");
	      with (removeButton){
	        id = "rm" + modifierCount;
	        type = "button";
	        addEventListener("click", function() {
	          modifierArray.removeModifier(this.id);
	        });
	        appendChild(buttonText);
	      }
	      with (modDiv)
	      {
	        appendChild(modifierTitle);
	        appendChild(rangeOfEffect);
				}
				if(document.getElementById("newModifierRangeOfEffect").value != "UK")
				{
					modDiv.appendChild(rangeEnter);
				}
				with (modDiv)
				{
	        appendChild(description);
	        appendChild(inputBox);
					appendChild(lineBreak);
	        appendChild(removeButton);
	      }
	      document.getElementById("modifierDiv").appendChild(modDiv);
	      break;
	    case "Voting System":
	      var description = document.createElement("p");
	      textNode = document.createTextNode("Choose the new system to apply:");
	      description.appendChild(textNode);
	      var systemSelect = document.createElement("select");
	      var option;
				var systemOptions = Array("Additional Member System", "Pure Proportional");
				for(var i = 0; i < systemOptions.length; i++)
				{
					option = document.createElement("option");
		      option.value = systemOptions[i];
		      option.text = systemOptions[i];
		      systemSelect.add(option);
				}
	      systemSelect.className = "primaryEffect";
	      var lineBreak = document.createElement("br");
	      var lineBreak2 = document.createElement("br");
	      removeButton = document.createElement("button");
	      var buttonText = document.createTextNode("Remove modifier");
	      with (removeButton)
	      {
	        id = "rm" + modifierCount;
	        type = "button";
	        addEventListener("click", function() {
	          modifierArray.removeModifier(this.id);
	        });
	        appendChild(buttonText);
	      }
	      with (modDiv)
	      {
	        appendChild(modifierTitle);
	        appendChild(rangeOfEffect);
	        appendChild(description);
	        appendChild(systemSelect);
	        appendChild(lineBreak);
	        appendChild(lineBreak2);
	        appendChild(removeButton);
	      }
	      document.getElementById("modifierDiv").appendChild(modDiv);
	      break;
	    case "Turnout":
	      var description = document.createElement("p");
	      textNode = document.createTextNode("Enter the value of the new turnout:");
	      description.appendChild(textNode);
	      var inputBox = document.createElement("input");
	      with (inputBox)
	      {
	        type = "text";
	        className = "primaryEffect";
	      }
				var br = document.createElement("br");
	      removeButton = document.createElement("button");
	      var buttonText = document.createTextNode("Remove modifier");
	      with (removeButton)
	      {
	        id = "rm" + modifierCount;
	        type = "button";
	        addEventListener("click", function() {
	          modifierArray.removeModifier(this.id);
	        });
	        appendChild(buttonText);
	      }
	      with (modDiv)
	      {
	        appendChild(modifierTitle);
	        appendChild(rangeOfEffect);
				}
				if(document.getElementById("newModifierRangeOfEffect").value != "UK")
				{
					modDiv.appendChild(rangeEnter);
				}
				with (modDiv)
				{
	        appendChild(description);
	        appendChild(inputBox);
					appendChild(br);
	        appendChild(removeButton);
	      }
	      document.getElementById("modifierDiv").appendChild(modDiv);
	      break;
	  }
		if(document.getElementById("modifierChoice").value != "Voting System")//This makes sure that the range of effect is correct for the voting system modifier.
		{
			modifierArray.addModifier(new Modifier(document.getElementById("modifierChoice").value, modifierCount, document.getElementById("newModifierRangeOfEffect").value, modDiv));//A new modifier object is instantiated and appended to the modifierArray.
		} else
		{
			modifierArray.addModifier(new Modifier(document.getElementById("modifierChoice").value, modifierCount, "UK", modDiv));
		}
	}

	function getJSON()
	{
		var JSONoutput = modifierArray.setDetails();//The modifier array calls it's modifiers to fetch it's info.  This fucntion then returns a JSON string.
	  return JSON.stringify(JSONoutput);//This returns the JSON string to the submit button which called this function.
	}
	</script>
</html>
