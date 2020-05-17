<html>
  <head>
  </head>
  <body>
    <?php
      class Election implements JsonSerializable
      {
        private $parties = Array();
        private $constituencies = Array();
        private $regions = Array();
        private $electionYear;
        private $electorate;
        private $turnout = Array(0, 0);
        private $DDPECode;
        private $electionError;
        private $modifierString;
        private $modifierDescription;

        public function __construct($electionYear, $modifierString)
        {
          $this->electionYear = $electionYear;//The election yaer is set.
          $this->modifierString = json_decode($modifierString);//The modifier string is parsed into a PHP object, so it can be read by the program.
          DataControl::$conn = new mysqli("localhost", "177548", "B4H47lGCcwGeNdIC", "simulatordb");
          DataControl::$conn->set_charset("utf8");//A connection to the database is made.
          switch ($this->electionYear)
          {
            case 2010:
              $this->DDPECode = 382037;
              break;
            case 2015:
              $this->DDPECode = 382386;
              break;
            case 2017:
              $this->DDPECode = 730039;
              break;
          }//The election object must have a code based off the Data.Parliament API service, this code allows for the correct election to be obtained, therefore needs to be hard coded.
          $this->setUpConstituencies();//After this, all of the constituency objects will have been set up.
          $this->setUpParties();//After this, all of the party objects will have been set up.
          $this->setUpRegions();//After this, all of the regional objects will have been set up and the constituencies transferred to them.
          $this->findConstituencyWinners();//All of the constituencies will have sorted their candidates properties so that the winners of the constituencies are found.
          $this->totalElection();//This ties the 'lose ends' of the election, so it is fully simulated.
          foreach($this->modifierString->modifierArray as $modifier)
          {//This loops checks for turnout.
            if($modifier instanceof stdClass)
            {//This checks to make sure the parsed PHP object is of stdClass, which is the correct object type for the object.
              if($modifier->type == "Turnout" && (DataControl::checkByRegex("/(100|[0-9]{1,2})/", $modifier->primaryEffect) && DataControl::checkByRegex("/\bUK\b|\bRegion\b|\bIndividual Constituency\b/", $modifier->rangeOfEffect)))
              {//Checking to see if the type is turnout, which the primary affect is a number between 0 and 100, whilst the range of effect is UK, Region, or Individual Constituency.
                $this->modifyTurnout($modifier->primaryEffect, $modifier->rangeOfEffect, $modifier->areaToEffect);//The turnout modifier is called and applied onto the election.
              }
            }
          }
          foreach($this->modifierString->modifierArray as $modifier)
          {//This loop checks for partisan dealignment.
            if($modifier instanceof stdClass)
            {
              if($modifier->type == "Partisan Dealignment" && (DataControl::checkByRegex("/(100|[0-9]{1,2})/", $modifier->primaryEffect) && DataControl::checkByRegex("/\bUK\b|\bRegion\b|\bIndividual Constituency\b/", $modifier->rangeOfEffect)))
              {//Check to see if the type is partisan dealignment and the input is a number between 0 and 100, and the range of effect is corret.
                $this->modifyDealignment($modifier->primaryEffect, $modifier->rangeOfEffect, $modifier->areaToEffect);//The partisan dealignment modifier is called on the election.
              }
            }
          }
          foreach($this->modifierString->modifierArray as $modifier)
          {//This loop checks for class, ethnicity, and age modifiers.
            if($modifier instanceof stdClass)
            {
              if((DataControl::checkByRegex("/(\b18-24\b|\b25-29\b|\b30-44\b|\b45-59\b|\b60-64\b|65\+)|(\bWhite British\b|\bBME\b|\bJewish\b|\bHindu\b|\bSikh\b|\bCatholic\b|\bProtestant\b)/", $modifier->primaryEffect) &&
              DataControl::checkByRegex("/-?(100|[0-9]{1,2})/", $modifier->secondaryEffect)) ||
              ($modifier->type == "Class" && DataControl::checkByRegex("/(\bAB\b|\bC1\b|\bC2\b|\bDE\b)/", $modifier->primaryEffect) &&
              (DataControl::checkByRegex("/-?(100|[0-9]{1,2})/", $modifier->secondaryEffect) || DataControl::checkByRegex("/(100|[0-9]{1,2})/", $modifier->tertiaryEffect))) &&
              DataControl::checkByRegex("/\bUK\b|\bRegion\b|\bIndividual Constituency\b/", $modifier->rangeOfEffect))
              {//These are the checks for the modifier.  Ethnicity and age are checked together because they both only have 2 effect effects.  Class is checked seperately because it has 3 effects where only the primary affect and secondary or tertiary effects
                //need to be entered.
                switch($modifier->type)
                {//This checks the type, because their are 3 different types a switch case is used, each case the associated modifier.
                  case "Age":
                    $this->modifyAge($modifier->primaryEffect, $modifier->secondaryEffect, $modifier->rangeOfEffect, $modifier->areaToEffect);
                    break;
                  case "Ethnicity":
                    $this->modifyEthnicity($modifier->primaryEffect, $modifier->secondaryEffect, $modifier->rangeOfEffect, $modifier->areaToEffect);
                    break;
                  case "Class":
                    $this->modifyClass($modifier->primaryEffect, $modifier->secondaryEffect, $modifier->tertiaryEffect, $modifier->rangeOfEffect, $modifier->areaToEffect);
                    break;
                }
              }
            }
          }
          foreach($this->modifierString->modifierArray as $modifier)
          {//This loop checks for an alternative voting system.
            if($modifier instanceof stdClass)
            {
              if($modifier->type == "Voting System" && DataControl::checkByRegex("/\bAdditional Member System\b|\bPure Proportional\b/", $modifier->primaryEffect))
              {//This returns true if the type is voting system and the primary effect is either Additional Member System or Pure Proportional.
                $this->modifyVotingSystem($modifier->primaryEffect);
              }
            }
          }
          $this->parties = DataControl::mergeSort($this->parties, 1);//Sorting the parties based on their seat count.
          echo $this->modifierDescription;//The modifier description is outputted onto the screen.
          echo "Election Error: ".$this->calculateElectionError()."%<br>";//THe election error is outputted to the screen.
      }


        private function setUpConstituencies()
        {
          $requestUrl = 'http://lda.data.parliament.uk/electionresults.json?electionId='.$this->DDPECode.'&_pageSize=650';
          $requestResult = DataControl::makeRequest($requestUrl);//An API request is being made based off the code previously assigned to this election.
          for($i=0; $i<650; $i++)//650 - for each constituency in the election.
          {
            $conName = $requestResult->result->items[$i]->constituency->label->_value;
            $conElectorate = $requestResult->result->items[$i]->electorate;
            $conCode = $requestResult->result->items[$i]->_about;//Data is being assigned from the php object.
            if(!(DataControl::checkByRegex("/[A\-Za\-z ',\-&]+/", $conName) || DataControl::checkByRegex("/[0\-9]+/", $conElectorate) ||
            DataControl::checkByRegex("/(\bhttp:\/\/data.parliament.uk\/resources\/\b){1}[0-9]{6}/", $conCode)))//Regular expressions are being used to make sure the data being received is acceptable for the program.
            {
              throw new Exception("API election request failed to get appropriate election data - $conName $conElectorate $conCode<br>");//An error is thrown if data is not correct, as the rest of the program relies on it.
            } else
            {
              $this->electorate += $conElectorate;//The electorate of the election is being generated through accumulating the electorates of all the constituencies.
              $this->constituencies[] = new Constituency($conName, $conElectorate, $conCode);//A new constituency object is being instantiated.
            }
          }
          $this->constituencies = DataControl::mergeSort($this->constituencies, 3);//THe constituencies are sorted alphabetically, so they are ready to be transferred into region objects.
        }

        private function setUpParties()
        {
          foreach($this->constituencies as $constituency)
          {
            $uniqueCheck = true;//This is being used as a flag to check if a party at any given point of the iteration is 'new' or not.  The party is assumed to be new unless proven otherwise.
            $curCans = $constituency->returnCandidates();
            foreach($curCans as $candidate)
            {
              foreach($this->parties as $party)//A loop to check if the party already exists or not.
              {
                if($candidate->getParty() == $party->getID() && count($this->parties) > 0)//If the candidate's party matches and the number of parties already in existance is greater than 0.
                {
                  $uniqueCheck = false;//The flag is set to false, the program will not create a new party.
                  continue;//This party is not new, the rest of this iteration can therefore be skipped entirely.
                }
              }
              if($uniqueCheck == true)//This party is new.
              {
                $this->parties[] = new Party($candidate->getParty(), $this->electionYear);//A new party is created/instantiated.
              } else {
                $uniqueCheck = true;//The flag is changed to assume the next party is unqiue.
              }
            }
          }
          foreach($this->constituencies as $constituency)//A new loop to assign candidate's to the appropriate party object.
          {
            $curCans = $constituency->returnCandidates();
            foreach($curCans as $candidate)
            {
              foreach($this->parties as $party)
              {
                if($candidate->getParty() == $party->getName(true))//If the candidate's party has the same ID as the party object.
                {
                  $candidate->setPartyObj($party);//The candidate's party property now becomes a party object instead of being a string ID.
                }
              }
            }
          }
        }

        private function setUpRegions()
        {
          $searchResult = null;
          $conName = "";
          $regionData = DataControl::readCSV("RegionalData");//Getting the data about each region from a local CSV file.
          foreach($regionData as $region)
          {
            $this->regions[] = new Region($region[0], $region[2], $region[3], $region[4], $region[5], $region[6], $region[7], $region[8], $region[9], $region[10], $region[11], $region[12], $region[13],
          $region[14], $region[15], $region[16], $region[17], $region[18], $region[19]);//A new region is instantiated
          }
          foreach($this->regions as $region)
          {
            $region->setUpConstituencies($this->constituencies);//A function that will transfer the constituency objects from election to their respective regions.
          }
        }

        private function findConstituencyWinners()
        {
          foreach($this->regions as $region)
          {
            $region->sortWinners(false);//Calls a method on each constituency within the region.
          }
        }

        private function totalElection()
        {
          foreach($this->regions as $region)
          {
            $this->turnout[0] += $region->applyResults();//The total election turnout is calculated.
          }
          $this->turnout[1] = round(($this->turnout[0]/$this->electorate) * 100, 0, PHP_ROUND_HALF_UP);//The turnout as a percentage is calculated by dividing the numerical turnout by the electorate.
          $this->parties = DataControl::mergeSort($this->parties, 1);//The parties are sorted based on their number of seats.
          foreach($this->parties as $party)
          {
            if($party->getSeats() == NULL)
            {
              $party->hasNoSeats(true);//This will set a parties seat value to 0, in replacement of null, which is to prevent errors occuring in future parts of the program.
            }
          }
        }

      private function calculateElectionError()//This calculates the mean average difference between the number of seats a party got compared to their votes.
      {
        $electionError = 0;
        $count = 0;
        foreach($this->parties as $party)
        {
          if($party->getSeats() > 0)//The aprty must have a seat to be included, because naturally if they didn't win seats they don't matter in this case but also to prevent a division by 0 error.
          {
            $electionError += (($party->getSeats()/650) * 100) - (($party->getVotes()/$this->turnout[0]) * 100);//The parties proportion of votes is taken away from their proportion of seats.
            $count++;//Count is incremented so that the program can correctly calculate an average.
          }
        }
        $electionError = round(($electionError / $count) * 100, 2, PHP_ROUND_HALF_UP);//The election error is divided by count to find the average difference between seats and votes.
        return $electionError;
      }

      public function jsonSerialize()//These are the proporties which will be parsed into the JSON string (red), and the object names they will go under (green).
      {
        return
        [
          'parties' => $this->parties,
          'regions' => $this->regions,
          'electionStats' => ["TurnoutInt" => $this->turnout[0], "TurnoutPercent" => $this->turnout[1]]
        ];
      }

      public function electionToJSON()
      {
        $output = json_encode($this);//The election object is being converted to a JSON string.
        $output = str_replace(array('\n', '\r'), "", $output);//Any special characters are replaced because they are not needed and will cause errors in other parts of the program.
        return $output;//The JSON is returned to an external script from where this object was created.
      }

      private function modifyVotingSystem($votingSystem)
      {
        $seatsBefore = Array(0, 0, 0);
        $seatsAfter = Array(0, 0, 0);//This happens in every modifier, 2 arrays holding the seats of the 3 main parties is created to show the effect of modifiers on the seat count of each party.
        foreach($this->parties as $party)
        {
          if($party->getName() == "Con")
          {
            $seatsBefore[0] = $party->getSeats();//Number of Conservative seats gotten.
          }
          if($party->getName() == "Lab")
          {
            $seatsBefore[1] = $party->getSeats();//Number of Labour seats gotten.
          }
          if($party->getName() == "LD")
          {
            $seatsBefore[2] = $party->getSeats();//Number of Liberal Democrats seats gotten.
          }
        }//This loop finds the seats before
        switch($votingSystem)
        {
          case "Pure Proportional":
            foreach($this->parties as $party)
            {
              $party->gainSeats(true, round(($party->getVotes() / $this->turnout[0]) * 650, 0, PHP_ROUND_HALF_UP));//If the modifier is for pure proportional, the parties seat count is changed to reflect the number of votes they got.
              //Constituencies are not accounted for in this case because they are irrelavent for this modifier.
            }
            break;
          case "Additional Member System":
            $this->parties = DataControl::mergeSort($this->parties, 3);//Parties have to be sorted alphabetically before they are used to add extra seats to.
            foreach($this->regions as $region)
            {
              $curRegSeats = null;
              $curRegSeats = $region->addAMSSeats();//Each region returns an array of party names, where each party name corresponds to an extra seat won for each party.
              foreach($curRegSeats as $party)
              {
                $partyMatch = DataControl::binarySearch($this->parties, 0, count($this->parties), $party);//For each party name in the 'seat' array, the party is then searched up using a binary search to then add a seat to it.
                $partyMatch->gainSeats(false, 0);
                unset($party);//The party is removed from the array to reduce the amount of memory it uses.
              }
            }
            foreach($this->parties as $party)
            {
              $party->AMSHalfSeats();//Each party then has it's seats halfed, this is because currently there are double the amount of seats that their currently should be in the system.  It ensures best representation of this system.
            }
            $seatCount = 0;
            break;
          }
          $this->parties = DataControl::mergeSort($this->parties, 1);//The parties array is sorted by each parties seat count.
          foreach($this->parties as $party)
          {
            if($party->getName() == "Con")
            {
              $seatsAfter[0] = $party->getSeats();
            }
            if($party->getName() == "Lab")
            {
              $seatsAfter[1] = $party->getSeats();
            }
            if($party->getName() == "LD")
            {
              $seatsAfter[2] = $party->getSeats();
            }
          }
          $this->modifierDescription .= "A change of voting system to ".$votingSystem." has led to the Conservatives ".
          ($seatsBefore[0] > $seatsAfter[0] ? " lost ".($seatsBefore[0] - $seatsAfter[0]) : " gained ".($seatsAfter[0] - $seatsBefore[0]))." seats.<br>  Labour ".
          ($seatsBefore[1] > $seatsAfter[1] ? " lost ".($seatsBefore[1] - $seatsAfter[1]) : " gained ".($seatsAfter[1] - $seatsBefore[1]))." seats.<br>  The Liberal Democrats ".
          ($seatsBefore[2] > $seatsAfter[2] ? " lost ".($seatsBefore[2] - $seatsAfter[2]) : " gained ".($seatsAfter[2] - $seatsBefore[2]))." seats. <br><br>";//This concatenates a new modifier description to the modifierDescription property.
          //This will be outputted to describe the effects that the modifier had on the seat counts.
        }

      private function modifyTurnout($change, $rangeOfEffect, $areaToEffect)
      {
        if($change != $this->turnout[1] && $change != 0)//This will not be true if the current turnout is the same as the new turnout to be entered, or if the new turnout is 0.
        {
            $origTurnout = $this->turnout;//The original turnout is recorded for the modifier description.
            $this->turnout[0] = 0;//The new turnout number is set to 0.
            foreach($this->regions as $region)
            {
              if($rangeOfEffect == "Region")//Checking for a regional range of effect.
              {
                if($region->getName() == $areaToEffect)//If it is a regional range of effect, the area to effect is checked to see if the current region on iteration is to be the one modified.
                {
                  $region->modifyTurnout($change, $rangeOfEffect, $areaToEffect);//Turnout modifier is called.
                }
              } else
              {
                $region->modifyTurnout($change, $rangeOfEffect, $areaToEffect);//If it is not regional range of effect, it's either UK or Individual Constituency, which can be handled at Region level.
              }
            }
            foreach($this->regions as $region)
            {
              $this->turnout[0] += $region->getTurnoutNum();//The new turnout number is totaled by adding together all of the regional turnout numbers.
            }
            $this->turnout[1] = round(($this->turnout[0] / $this->electorate) * 100, 1, PHP_ROUND_HALF_UP);//The new turnout as a percentage is calculated.
            $this->modifierDescription .= "A turnout of ".$change."% has lead to a".($change > $origTurnout[1] ? ("n increase") : (" decrease")).
            " of ".($this->turnout[0] > $origTurnout[0] ? ($this->turnout[0] - $origTurnout[0]) : ($origTurnout[0] - $this->turnout[0]))." voters voting.<br><br>";//The modifier description is added to the modifierDescription property, stating the difference
            //between the old turnout and the new turnout.
          }
      }

      private function modifyDealignment($change, $rangeOfEffect, $areaToEffect)
      {
        if($change > 0)//Will only trigger if the dealignment input is higher than 0.
        {
          $currentMainVotes = 0;
          $newMainVotes = 0;//These variables are for finding the difference in votes in the Labour and Conservative parties before and after the modifier has been applied.
          foreach($this->parties as $party)
          {
            if($party->getName() == "Lab" || $party->getName() == "Con") $currentMainVotes += $party->getVotes();//The number of votes the Labour and Conservative parties has is added together.
          }
          foreach($this->regions as $region)
          {
            if($rangeOfEffect == "Region")
            {
              if($region->getName() == $areaToEffect)
              {
                $region->modifyDealignment($change, $rangeOfEffect, $areaToEffect);//The dealignment modifier is called for a regional range of effect.
              }
            } else
            {
              $region->modifyDealignment($change, $rangeOfEffect, $areaToEffect);//The dealignment modifier is called for an Individual Constituency or UK range of effect.
            }
          }
          foreach($this->parties as $party)
          {
            if($party->getName() == "Lab" || $party->getName() == "Con") $newMainVotes += $party->getVotes();//The votes for Labour and Conservative are totalled after the modifier was applied.
          }
          $this->modifierDescription .= "Partisan dealignment being set to ".$change."% has to a".
          ($newMainVotes > $currentMainVotes ? "n increase of ".($newMainVotes - $currentMainVotes) : " decrease of ".($currentMainVotes - $newMainVotes))." votes for Conservative and Labour parties.<br><br>";//A new modifier description is appended to the
          //modifierDescription property, which states the difference between the number of votes for Labour and Conservative before and after the modifier is applied.
        }
      }

      private function modifyClass($class, $change, $alignmentChange, $rangeOfEffect, $areaToEffect)
      {
        if($change > 0)//This will only execute if the increase in proportion is bigger than 0.
        {
          $seatsBefore = Array(0, 0, 0);
          $seatsAfter = Array(0, 0, 0);
          foreach($this->parties as $party)
          {
            if($party->getName() == "Con")
            {
              $seatsBefore[0] = $party->getSeats();
            }
            if($party->getName() == "Lab")
            {
              $seatsBefore[1] = $party->getSeats();
            }
            if($party->getName() == "LD")
            {
              $seatsBefore[2] = $party->getSeats();
            }
          }
          foreach($this->regions as $region)
          {
            if($rangeOfEffect == "Region")
            {
              if($region->getName() == $areaToEffect)
              {
                $region->modifyClass($class, $change, $alignmentChange, $rangeOfEffect, $areaToEffect);//This will call the class modifier for the specific region given.
              }
            } else
            {
              $region->modifyClass($class, $change, $alignmentChange, $rangeOfEffect, $areaToEffect);//This will call the class modifier for the Individual Constituency or UK range of effect.
            }
          }
          foreach($this->parties as $party)
          {
            if($party->getName() == "Con")
            {
              $seatsAfter[0] = $party->getSeats();
            }
            if($party->getName() == "Lab")
            {
              $seatsAfter[1] = $party->getSeats();
            }
            if($party->getName() == "LD")
            {
              $seatsAfter[2] = $party->getSeats();
            }
          }
          $this->modifierDescription .= "A".($change > 0 ? "n increase" : " decrease")." of ".$class." voters has led to the Conservatives ".
          ($seatsBefore[0] > $seatsAfter[0] ? " lost ".($seatsBefore[0] - $seatsAfter[0]) : " gained ".($seatsAfter[0] - $seatsBefore[0]))." seats.<br>  Labour ".
          ($seatsBefore[1] > $seatsAfter[1] ? " lost ".($seatsBefore[1] - $seatsAfter[1]) : " gained ".($seatsAfter[1] - $seatsBefore[1]))." seats.<br>  The Liberal Democrats ".
          ($seatsBefore[2] > $seatsAfter[2] ? " lost ".($seatsBefore[2] - $seatsAfter[2]) : " gained ".($seatsAfter[2] - $seatsBefore[2]))." seats. <br><br>";//A modifier description is created to show how the Conservatives, Labour, and Liberal Democrats
          //seats were affected.
        }
      }

      private function modifyEthnicity($group, $change, $rangeOfEffect, $areaToEffect)
      {
        if($change > 0)
        {
          $seatsBefore = Array(0, 0, 0);
          $seatsAfter = Array(0, 0, 0);
          foreach($this->parties as $party)
          {
            if($party->getName() == "Con")
            {
              $seatsBefore[0] = $party->getSeats();
            }
            if($party->getName() == "Lab")
            {
              $seatsBefore[1] = $party->getSeats();
            }
            if($party->getName() == "LD")
            {
              $seatsBefore[2] = $party->getSeats();
            }
          }
          foreach($this->regions as $region)
          {
            if($rangeOfEffect == "Region")
            {
              if($region->getName() == $areaToEffect)
              {
                $region->modifyEthnicity($group, $change, $rangeOfEffect, $areaToEffect);//This calls the ethnicity modifier for the regional range of effect.
              }
            } else
            {
              $region->modifyEthnicity($group, $change, $rangeOfEffect, $areaToEffect);//This calls the ethnicity modifier for the Individual Constituency or UK range of effect.
            }
          }
          foreach($this->parties as $party)
          {
            if($party->getName() == "Con")
            {
              $seatsAfter[0] = $party->getSeats();
            }
            if($party->getName() == "Lab")
            {
              $seatsAfter[1] = $party->getSeats();
            }
            if($party->getName() == "LD")
            {
              $seatsAfter[2] = $party->getSeats();
            }
          }
          $this->modifierDescription .= "A".($change > 0 ? "n increase" : " decrease")." of ".$group." voters has led to the Conservatives ".
          ($seatsBefore[0] > $seatsAfter[0] ? " lost ".($seatsBefore[0] - $seatsAfter[0]) : " gained ".($seatsAfter[0] - $seatsBefore[0]))." seats.<br>  Labour ".
          ($seatsBefore[1] > $seatsAfter[1] ? " lost ".($seatsBefore[1] - $seatsAfter[1]) : " gained ".($seatsAfter[1] - $seatsBefore[1]))." seats.<br>  The Liberal Democrats ".
          ($seatsBefore[2] > $seatsAfter[2] ? " lost ".($seatsBefore[2] - $seatsAfter[2]) : " gained ".($seatsAfter[2] - $seatsBefore[2]))." seats. <br><br>";//This will append another modifier description to the modifierDescription property, stating
          //the change in seat count for the Conservatives, Labour, and Lib Dems.
        }
      }

      private function modifyAge($groupToChange, $change, $rangeOfEffect, $areaToEffect)
      {
        if($change > 0)//This block will only execute if the secondary effect is bigger than 0.
        {
          $seatsBefore = Array(0, 0, 0);
          $seatsAfter = Array(0, 0, 0);
          foreach($this->parties as $party)
          {
            if($party->getName() == "Con")
            {
              $seatsBefore[0] = $party->getSeats();
            }
            if($party->getName() == "Lab")
            {
              $seatsBefore[1] = $party->getSeats();
            }
            if($party->getName() == "LD")
            {
              $seatsBefore[2] = $party->getSeats();
            }
          }//The parties seat counts are stored before the modifier is applied.
          foreach($this->regions as $region)
          {
            if($rangeOfEffect == "Region")
            {
              if($region->getName() == $areaToEffect)
              {
                $region->modifyAge($groupToChange, $change, $rangeOfEffect, $areaToEffect);//This will execute for a regional range of effect where the the area to affect is the same as the name of the region currently on iteration.
              }
            } else
            {
              $region->modifyAge($groupToChange, $change, $rangeOfEffect, $areaToEffect);//This is the modifier for the Individual Constituency or UK range of effect.
            }
          }
          foreach($this->parties as $party)
          {
            if($party->getName() == "Con")
            {
              $seatsAfter[0] = $party->getSeats();
            }
            if($party->getName() == "Lab")
            {
              $seatsAfter[1] = $party->getSeats();
            }
            if($party->getName() == "LD")
            {
              $seatsAfter[2] = $party->getSeats();
            }
          }//The parties seat counts are found after the modifier has taken place.
          $this->modifierDescription .= "A".($change > 0 ? "n increase" : " decrease")." of ".$groupToChange." voters has led to the Conservatives ".
          ($seatsBefore[0] > $seatsAfter[0] ? " lost ".($seatsBefore[0] - $seatsAfter[0]) : " gained ".($seatsAfter[0] - $seatsBefore[0]))." seats.<br>  Labour ".
          ($seatsBefore[1] > $seatsAfter[1] ? " lost ".($seatsBefore[1] - $seatsAfter[1]) : " gained ".($seatsAfter[1] - $seatsBefore[1]))." seats.<br>  The Liberal Democrats ".
          ($seatsBefore[2] > $seatsAfter[2] ? " lost ".($seatsBefore[2] - $seatsAfter[2]) : " gained ".($seatsAfter[2] - $seatsBefore[2]))." seats. <br><br>";//A new description is added to the modifierDescription which states the affect of the modifiers
          //on the 3 main parties seat count.
        }
      }
    }

      class Candidate implements JsonSerializable
      {
        private $firstNames;
        private $surname;
        private $votes;
        public $party;

        public function __construct($name, $votes, $party)
        {
          $name = str_replace("'", "", $name);//This removes any ' characters from the string, and this is so the name doesn't cause JSON errors when the JSON string is created.
          $splitName = explode(' ', $name);//The name is split into the first name and last name so it can be put into properties.
          $this->firstNames = $splitName[1];
          $this->surname = $splitName[0];//The name comes as surname then forename, so the 1st element of the array is set to the forname, and the 0th set to the surname.
          $this->votes = $votes;
          $this->party = str_replace("'", "", $party);//The party has any ' characters removed because of the JSON.
          $this->firstNames = str_replace(",", "", $this->firstNames);
          $this->surname = str_replace(",", "", $this->surname);//Both the surname and the forenames have an commas removed from it.
          if($this->firstNames == "Andy" && $this->surname == "Stamp	")//Andy Stamp specifically caused issues with extracting the election results because of poor APIs from DDP, there his surname needs to have the large blank space removed from it.
          {
            $this->surname = "Stamp";
          }
        }

        public function getVotes()
        {
          return $this->votes;//Getter for votes property.
        }

        public function getName()
        {
          return $this->firstNames." ".$this->surname;//Getter for name property, it returns the full name.
        }

        public function getParty()
        {
          return $this->party;//Getter for the party property.
        }

        public function setPartyObj($party)
        {
          $this->party = $party;//This will set the party property to an object rather than the initial string to was (representing it's original ID).
        }

        public function addVotes($change)
        {
          $this->votes += $change;//The votes property is incremented.
          $this->party->addVotes($change);//The party the candidate represents has its votesWon property incremented by the same value.
        }

        public function setVotes($votes)
        {
          if($this->votes != 0)
          {
            $this->setVotesToZero();//If the candidates votes property doesn't already equal 0, then its votes should be set to 0.
          }
          $this->votes = $votes;
          $this->party->addVotes($votes);//The votes property is set to the new value and the party has the new votes added to it.
        }

        public function setVotesToZero()
        {
          $this->party->addVotes(($this->votes)*-1);//The party has the candidates votes removed from its votesWon property, by adding a negative to it.
          $this->votes = 0;//Candidates votes set to 0.
        }

        public function applyVotes()
        {
          $this->party->addVotes($this->votes);//The value of the votes property is added to the party object referenced in the party property.
        }

        public function modifyTurnout($change, $curTurnout)//Takes 2 arguments, the new turnout, and the current turnout of the constituency the candidate is contesting.
        {
          $newVotes = 0;
          $newVotes = round(($this->votes * (100/($curTurnout * 100))) / (100/$change), 0, PHP_ROUND_HALF_DOWN);//The candidate vote total is increased to get a 'maximum' vote share, taking into account the turnout of the constituency.
          //It is then divided using the new turnout, so it decreased to get the necessary value.
          $this->setVotes($newVotes);//THe candidates votes are set to the newly calculated votes.
          return $this->votes;//The votes property is returned to the constituency object.
        }

        public function jsonSerialize()//This is called when json_encode() is called on this object.
        {
          return
          [
            'fornames' => $this->firstNames,
            'surname' => $this->surname,
            'votes' => $this->votes,
            'party' => $this->party->getName()//These are the properties which are parsed into a JSON string, the 'green' is their object name, and the 'red' is the reference to the actual value.
          ];
        }
      }

      class Party implements JsonSerializable
      {
        private $partyID;
        private $partyName;
        private $partyLeader;
        private $isMain;
        private $votesWon;
        private $seatsWon;

        public function __construct($partyID, $electionYear)
        {
          $this->partyID = str_replace("'", "", $partyID);//This removes an ' characters from the party ID brought in from the DDP API.
          if(file_exists("Party Details/" . $this->partyID . " " . $electionYear . ".txt"))//This check determines whether a party is main or not, does a file exist which gives the object extra data to store.
          {
            $this->isMain = true;//The party is now a main one.
            $partyDetails = fopen("Party Details/" . $this->partyID . " " . $electionYear . ".txt", "r");//The file containing the extra party details is opened in read only mode.
            $this->partyName = fgets($partyDetails);//The first line of the file contains the full party name.
            $this->partyLeader = fgets($partyDetails);//The second line of the file contains the party leader name.
            fclose($partyDetails);//The file is closed.
          } else
          {
            $this->partyName = $this->partyID;
            $this->isMain = false;//If the party is not main, its ID is set to the name, and isMain is set to false.
          }
        }

        public function getName()
        {
          if($this->isMain == true && func_get_args() == 0)//If there are no functions within the argument, return the party name, as the party is also main.
          {
            return $this->partyName;
          } else
          {
            return $this->partyID;//If there is more than 0 functions passed into the argument, then, and the party is not main, return the party ID.
          }
        }

        public function getID()
        {
          return $this->partyID;//Getter for the party ID.
        }

        public function addVotes($votesWon)
        {
          $this->votesWon += $votesWon;//Adds votes passed into the method to the votesWon property.
        }

        public function getVotes()
        {
          return $this->votesWon;//Getter for the amount of votes each party won.
        }

        public function gainSeats($multiple, $seats)//2 arguments, multiple stores whether the party is to have more than 1 seat added for removed from the seatsWon property.
        {
          if(!$multiple)
          {
            $this->seatsWon += 1;//if not multiple, a seat is added to the parties seat total.
          } else
          {
            $this->seatsWon = $seats;//If multiple is true, then the seatsWon property is set to the seats parameter passed into the function.
          }
        }

        public function getSeats()
        {
          return $this->seatsWon;//Getter for seatsWon property.
        }

        public function AMSHalfSeats()
        {
          $this->seatsWon = round($this->seatsWon/2, 0, PHP_ROUND_HALF_UP);//This will half the number of seats the party has as part of the Additional Member System modifier.
        }

        public function hasNoSeats($hasSeats)//Will be set to false when called.
        {
          if($this->seatsWon == NULL || $hasSeats == false)//If the partys seat count is NULL, because they don't have any seats, then it should be seat to 0, which is to prevent JSON errors.
          {
            $this->seatsWon = 0;
          }
        }

        public function jsonSerialize()//This is executed when json_encode is called on this object.
        {
          return
          [
            'partyName' => $this->partyName,
            'partyLeader' => $this->partyLeader,
            'isMain' => $this->isMain,
            'seatsWon' => $this->seatsWon,
            'votesWon' => $this->votesWon//These are the properties to be encoded into the JSON string.
          ];
        }
      }

      class Region implements JsonSerializable
      {
        private $name;
        private $constituencies = Array();
        private $turnout = Array(0, 0);
        private $electorate;
        private $classDetails = Array();//This stores all of the social class details read from the CSV file.
        private $ethnicityDetails = Array();//This stores all of the ethnic group details read from the CSV file.
        private $ageDetails = Array();//This stores all of the age group details read from the CSV file.

        public function __construct($name, $AB, $C1, $C2, $DE, $whiteBritish, $BME,
        $jewish, $hindu, $sikh, $muslim, $catholic, $protestant, $age1824, $age2529, $age3044, $age4559, $age6064, $age65plus)//All of the details are passed into the constructor.
        {
          $this->name = $name;
          $this->classDetails["AB"] = $AB;
          $this->classDetails["C1"] = $C1;
          $this->classDetails["C2"] = $C2;
          $this->classDetails["DE"] = $DE;
          $this->ethnicityDetails["White British"] = $whiteBritish;
          $this->ethnicityDetails["BME"] = $BME;
          $this->ethnicityDetails["Jewish"] = $jewish;
          $this->ethnicityDetails["Hindu"] = $hindu;
          $this->ethnicityDetails["Sikh"] = $sikh;
          $this->ethnicityDetails["Muslim"] = $muslim;
          $this->ethnicityDetails["Catholic"] = $catholic;
          $this->ethnicityDetails["Protestant"] = $protestant;
          $this->ageDetails["18-24"] = $age1824;
          $this->ageDetails["25-29"] = $age2529;
          $this->ageDetails["30-44"] = $age3044;
          $this->ageDetails["45-59"] = $age4559;
          $this->ageDetails["60-64"] = $age6064;
          $this->ageDetails["65+"] = $age65plus;//All of the details are pushed to the relavent properties where each is given it's own index, which corresponds to the primary affect of each of the modifiers on HomePage.php.
          $this->ageDetails["SUM"] = 0;//This is the value which is used to raise the age group values to fully represent the electorate.
          foreach($this->ageDetails as $group => $detail)
          {
            if($group != "SUM")
            {
              $this->ageDetails["SUM"] += $detail;//All of the age group details are added together.
            }
          }
          $raiseValue = 100 / $this->ageDetails["SUM"];//The value required to make sure all of the age group details total 100 is found.
          foreach($this->ageDetails as $group => $detail)
          {
            if($group != "SUM")
            {
              $detail = round($detail * $raiseValue, 1, PHP_ROUND_HALF_DOWN);//Each value in the age group array is multiplied by the raise value, which means when totalled it makes up 100% of the electorate.
            }
          }
        }

        public function setUpConstituencies(&$constituencies)//The constituencies property from the Election object is passed by reference.
        {
          $count = 0;
          DataControl::makeDBRequest("SELECT `Name` FROM `regions` WHERE `Region` = '$this->name'");//All of the constituencies which are in the namely instantiated region are found from the database.
          do {
            $nextCon = DataControl::returnNext();//The name of the next constituency to be aggregated is gotten.
            if($nextCon != false)
            {

              $this->constituencies[] = DataControl::binarySearch($constituencies, 0, count($constituencies) - 1, $nextCon["Name"]);//The constituency is search for within the constituency argument passed into the method, the returned result is the constituency object.
              $count = 0;//This variable is used to find the index of the object being searched for, so it can later be removed.
              foreach($constituencies as $constituency)
              {
                if($constituency->getName() == $nextCon["Name"])
                {
                  array_splice($constituencies, $count, 1);//If the constituency name is the same as the constituency which has just been aggregated to the region, it is removed from the constituencies property of the election.
                }
                $count++;//Count is incremented to match the new index of the constituency being sequentially searched for.
              }
            }
          } while($nextCon != false);//This loops whilst there are still constituencies to be aggregated into this region.
          foreach($this->constituencies as $constituency)
          {
            $this->electorate += $constituency->getElectorate();//The electorate is totaled to match that of all the constituencies within the region.
            $constituency->setRegion($this->name);//The constituencies region property is set to the name of this region object.
          }
        }

        public function sortWinners($afterMod)//After mod - is this method called after applying a modifier or not.
        {
          foreach($this->constituencies as $constituency)
          {
            $constituency->sortWinner($afterMod);//The sortWinner method is called onto all constituencies, passing the value of $afterMod into it.
          }
        }

        public function applyResults()
        {
          foreach($this->constituencies as $constituency)
          {
            $constituency->applyResults();
            $this->turnout[0] += $constituency->getTurnoutNum();//Each constituency applies the candidate results to their parties, and then it's turnout it found.
          }
          $this->turnout[1] = round($this->turnout[0]/$this->electorate, 1, PHP_ROUND_HALF_UP);//The turnout as a percentage is found for the constituency.
          return $this->turnout[0];//The turnout as a number is returned to the Election object calling this.
        }

        public function addAMSSeats()
        {
          $parties = Array();//An associative array containing the names and vote count of all the parties in the region.
          foreach($this->constituencies as $constituency)
          {
            foreach($constituency->returnCandidates() as $candidate)//Each constituencies then their candidates property is iterated through.
            {
              $check = true;//This assumes every party that is checked has not be seen before.
              foreach($parties as $partyName => $votes)
              {
                if($partyName == $candidate->party->getName())//Each of the parties already in the parties array is checked to see if the party of the current candidate is new or not.
                {
                  $check = false;//The check variable is set to false to let the program know that this party is already in the parties list.
                }
              }
              if($check == true)
              {
                $parties += [$candidate->party->getName() => $candidate->party->getVotes()];//If the party has not been seen before, then its name is added to the array as a key and its votesWon property as the value.
              }
            }
          }
          $parties = DataControl::DHondt($parties, count($this->constituencies), Array());//The DHondt method is called on the parties array, which will then use it to create an array of new parties who need extra seats under AMS.
          return $parties;//The new parties array is returned back to the Election object.
        }

        public function getName()
        {
          return $this->name;//Getter for the name property.
        }

        public function getTurnoutNum()
        {
          return $this->turnout[0];//Getter for the turnout in numbers.
        }

        public function getTurnoutPercentage()
        {
          return $this->turnout[1];//Getter for the turnout percetage.
        }

        public function jsonSerialize()//This is called when json_encode is called on this object.
        {
          return
          [
            'name' => $this->name,
            'constituencies' => $this->constituencies,
            'turnoutNumber' => $this->turnout[0],
            'turnoutPercentage' => $this->turnout[1]//This object will be encoded into the name, constituencies, and both both elements of the turnout property.
          ];
        }

        public function modifyTurnout($change, $rangeOfEffect, $areaToEffect)
        {
          $this->turnout[0] = 0;
          foreach($this->constituencies as $constituency)
          {
            if($rangeOfEffect == "Individual Constituency")
            {
              if($constituency->getName() == $areaToEffect)
              {
                $this->turnout[0] += $constituency->modifyTurnout($change);//This is for the Individual Constituency range of effect, where the current constituency on iteration is the one to have the modifier applied to.
              } else
              {
                $this->turnout[0] += $constituency->getTurnoutNum();//If the constituency is not the correct one for the are of effect, thenit's turnout as a number is added to the turnout of the region.
              }
            } else
            {
              $this->turnout[0] += $constituency->modifyTurnout($change);//Modifier applied on the constituency based on the UK range of effect.
            }
          }
          $this->turnout[1] = round(($this->turnout[0]/$this->electorate) * 100, 1, PHP_ROUND_HALF_UP);//The turnout as a percentage is calculated.
          $this->sortWinners(true);//The candidates of each constituency is resorted in the case of a potential winner, not necessary for this modifier but if there as a precaution but could also highlight any errors.
        }

        public function modifyDealignment($change, $rangeOfEffect, $areaToEffect)
        {
          if($change != 0)//If the change for dealignment is not equal to 0, then the code will execute.
          {
            foreach($this->constituencies as $constituency)
            {
              if($rangeOfEffect == "Individual Constituency")
              {
                if($constituency->getName() == $areaToEffect)
                {
                  $constituency->modifyDealignment($change);//This is for the Individual Constituency range of effect, where the current constituency on iteration is the one to have the modifier applied to.
                }
              } else
              {
                $constituency->modifyDealignment($change);//Modifier applied on the constituency based on the UK range of effect.
              }
            }
          }
        }

        public function modifyClass($class, $change, $alignmentChange, $rangeOfEffect, $areaToEffect)
        {
          foreach($this->constituencies as $constituency)
          {
            if($rangeOfEffect == "Individual Constituency")
            {
              if($constituency->getName() == $areaToEffect)
              {
                $constituency->modifyClass($class, $this->classDetails[$class], $change, $alignmentChange);//This is for the Individual Constituency range of effect, where the current constituency on iteration is the one to have the modifier applied to.
              }
            } else
            {
              $constituency->modifyClass($class, $this->classDetails[$class], $change, $alignmentChange);//Modifier applied on the constituency based on the UK range of effect.
            }
          }
        }

        public function modifyEthnicity($group, $change, $rangeOfEffect, $areaToEffect)
        {
          if($change != 0)
          {
            foreach($this->constituencies as $constituency)
            {
              if($rangeOfEffect == "Individual Constituency")
              {
                if($constituency->getName() == $areaToEffect)
                {
                  if($this->name != "Northern Ireland")
                  {
                    if(!($group == "Catholic" || $group == "Protestant"))//Catholic and Protestant modifiers do not have any effect outside of Northern Ireland.
                    {
                      $constituency->modifyEthnicity($group, $this->ethnicityDetails[$group], $change);//The modifider for ethnicity is called on an consituency which is not in Northern Ireland.
                    }
                  } else
                  {
                    $constituency->modifyEthnicity($group, $this->ethnicityDetails[$group], $change);
                  }
                }
              } else
              {
                if($this->name != "Northern Ireland")
                {
                  if(!($group == "Catholic" || $group == "Protestant"))
                  {
                    $constituency->modifyEthnicity($group, $this->ethnicityDetails[$group], $change);
                  }
                } else
                {
                  $constituency->modifyEthnicity($group, $this->ethnicityDetails[$group], $change);
                }
              }
            }
          }
        }

        public function modifyAge($groupToChange, $change, $rangeOfEffect, $areaToEffect)
        {
          if($change != 0)
          {
            foreach($this->constituencies as $constituency)
            {
              if($rangeOfEffect == "Individual Constituency")
              {
                if($constituency->getName() == $areaToEffect)
                {
                  $constituency->modifyAge($groupToChange, $change, $this->ageDetails[$groupToChange]);
                }
              } else
              {
                $constituency->modifyAge($groupToChange, $change, $this->ageDetails[$groupToChange]);
              }
            }
          }
        }
      }

      class Constituency implements JsonSerializable
      {
        private $DDPCCode;
        private $name;
        private $electorate;
        private $region;
        private $turnout = Array(0, 0);
        private $candidates = Array();

        public function __construct($name, $electorate, $DDPCCode)
        {
          $this->name = $name;
          $this->electorate = $electorate;
          $this->DDPCCode = substr($DDPCCode, -6);//The last 6 digits of the URL is taken, this allows an API call to be made using this code to find the correct constituency for this election.
          $this->setUpCandidates();
        }

        private function setUpCandidates()
        {
          $url = 'http://lda.data.parliament.uk/electionresults/'.$this->DDPCCode.'.json';//An API call is made with the code extracted from the previous URL given.
          $candidatesResults = DataControl::makeRequest($url);
          foreach($candidatesResults->result->primaryTopic->candidate as $candidate)
          {
            $canName = $candidate->fullName->_value;
            $canNoOfVotes = $candidate->numberOfVotes;
            $canParty = $candidate->party->_value;
            if(!(DataControl::checkByRegex("/([A\-Za\-z\- ]+)+/", $canName) || DataControl::checkByRegex("/([0\-9]+)/", $canNoOfVotes) || DataControl::checkByRegex("/([A\-Za\-z]+)/", $canParty)))//Each value obtained from the call is checked to make sure it's an appropriate piece of data.
            {
              throw new Exception("API election request failed to get appropriate candidate data - $canName $canNoOfVotes $canParty");//An error is thrown with the data that caused it if the data is not adequate, as the rest of the program relies on it.
            } else
            {
              $this->turnout[0] += $canNoOfVotes;
              $this->candidates[] = new Candidate($canName, $canNoOfVotes, $canParty);
            }
          }
          $this->turnout[1] = round(($this->turnout[0]/$this->electorate), 2, PHP_ROUND_HALF_UP);
        }

        public function sortWinner($afterMod)
        {
          if($afterMod)
          {
            $curWinner = $this->candidates[0];
            $this->candidates = DataControl::mergeSort($this->candidates, 2);
            if($this->candidates[0] != $curWinner)
            {
              $curWinner->party->gainSeats(true, $curWinner->party->getSeats() - 1);
              $this->candidates[0]->party->gainSeats(false, 0);
            }
          } else
          {
            $this->candidates = DataControl::mergeSort($this->candidates, 2);
            $this->candidates[0]->party->gainSeats(false, 0);
          }
        }

        public function getName()
        {
          return $this->name;
        }

        public function setRegion($region)
        {
          $this->region = $region;
        }

        public function returnCandidates()
        {
          return $this->candidates;
        }

        public function getElectorate()
        {
          return $this->electorate;
        }

        public function getTurnoutNum()
        {
          return $this->turnout[0];
        }

        public function getTurnoutPercentage()
        {
          return $this->turnout[1];
        }

        public function applyResults(){
          foreach($this->candidates as $candidate)
          {
            $candidate->applyVotes();
          }
        }

        public function modifyTurnout($change)
        {
          $this->turnout[0] = 0;
          foreach($this->candidates as $candidate)
          {
            $this->turnout[0] += $candidate->modifyTurnout($change, $this->turnout[1]);
          }
          $this->turnout[1] = round(($this->turnout[0]/$this->electorate), 1, PHP_ROUND_HALF_UP);
          return $this->turnout[0];
        }

        public function modifyDealignment($change)
        {
          $votePool = 0;
          $mainVotes = 0;
          $mainShare = 0;
          $leftOverVotes = 0;
          $leftOverShare = 0;
          $leftOverCans = 0;
          $ConCheck = false;
          $LabCheck = false;
          $LibCheck = false;
          $SNPCheck = false;
          $DUPCheck = false;
          $SFCheck = false;
          $UUPCheck = false;
          $SDLPCheck = false;
          $mainParty1 = "";
          $mainParty2 = "";
          $canPartyShare = Array();
            foreach($this->candidates as $candidate)
            {
              if((($candidate->party->getName() == "Con" || $candidate->party->getName() == "Lab") && $this->region !== "Northern Ireland")
              || ($this->region == "Northern Ireland" && ($candidate->party->getName() == "DUP" || $candidate->party->getName() == "SF")))
              {
                $mainShare += $candidate->getVotes()/$this->turnout[0];
              } else
              {
                $leftOverShare += $candidate->getVotes()/$this->turnout[0];
                $leftOverCans++;
              }
              $canPartyShare[$candidate->party->getName()] = $candidate->getVotes()/$this->turnout[0];
              $candidate->setVotesToZero();
            }
            $mainVotes = $this->turnout[0] * (1 - $change/100);
            $leftOverVotes = $this->turnout[0] - $mainVotes;
            if($this->region == "Northern Ireland")
            {
              {
                foreach($canPartyShare as $party => $share)
                {
                  if($party == "DUP")
                  {
                    $DUPCheck = true;
                  }
                  if($party == "SF")
                  {
                    $SFCheck = true;
                  }
                  if($party == "UUP")
                  {
                    $UUPCheck = true;
                  }
                  if($party == "SDLP")
                  {
                    $SDLPCheck == true;
                  }
                }
              }
              if($DUPCheck == true && $SFCheck == true)
              {
                $canPartyShare["DUP"] += $mainShare/2;
                $canPartyShare["SF"] += $mainShare/2;
                $mainParty1 = "DUP";
                $mainParty2 = "SF";
              } elseif($DUPCheck == true)
              {
                $canPartyShare["DUP"] += $mainShare/2;
                $canPartyShare["SDLP"] += $mainShare/2;
                $mainParty1 = "DUP";
                $mainParty2 = "SDLP";
              } elseif($SFCheck == true)
              {
                $canPartyShare["SF"] += $mainShare/2;
                $mainParty1 = "SF";
                if($UUPCheck == true)
                {
                  $canPartyShare["UUP"] += $mainShare/2;
                  $mainParty2 = "UUP";
                } else
                {
                  $canPartyShare["Alliance"] += $mainShare/2;
                  $mainParty2 = "Alliance";
                }
              } else
              {
                if($UUPCheck == true)
                {
                  $canPartyShare["UUP"] += $mainShare/2;
                  $mainParty1 = "UUP";
                } else
                {
                  $canPartyShare["Alliance"] += $mainShare/2;
                  $mainParty1 = "Alliance";
                }
                $canPartyShare["SDLP"] += $mainShare/2;
                $mainParty2 = "SDLP";
              }
            } else
            {
              foreach($canPartyShare as $party => $share)
              {
                if($party == "Lab")
                {
                  $LabCheck = true;
                }
                if($party == "Con")
                {
                  $ConCheck = true;
                }
                if($party == "LD")
                {
                  $LibCheck = true;
                }
                if($party == "SNP")
                {
                  $SNPCheck == true;
                }
              }
              if($ConCheck == true && $LabCheck == true)
              {
                $canPartyShare["Con"] += $mainShare/2;
                $canPartyShare["Lab"] += $mainShare/2;
                $mainParty1 = "Con";
                $mainParty2 = "Lab";
              } elseif($ConCheck == true)
              {
                $canPartyShare["Con"] += $mainShare/2;
                $mainParty1 = "Con";
                if($this->region == "Scotland" && $SNPCheck == true)
                {
                  $canPartyShare["SNP"] += $mainShare/2;
                  $mainParty2 = "SNP";
                } elseif($LibCheck = true)
                {
                  $canPartyShare["LD"] += $mainShare/2;
                  $mainParty2 = "LD";
                }
              } elseif($LabCheck == true)
              {
                $canPartyShare["Lab"] += $mainShare/2;
                $mainParty1 = "Lab";
                if($this->region == "Scotland" && $SNPCheck == true)
                {
                  $canPartyShare["SNP"] += $mainShare/2;
                  $mainParty2 = "SNP";
                } elseif($LibCheck = true)
                {
                  $canPartyShare["LD"] += $mainShare/2;
                  $mainParty2 = "LD";
                }
              }
            }
            foreach($canPartyShare as $party => $share)
            {
              if($party != $mainParty1 || $party != $mainParty2)
              {
                $share += $leftOverShare/$leftOverCans;
              }
            }
            foreach($this->candidates as $candidate)
            {
              if($candidate->party->getName() == "Con" || $candidate->party->getName() == "Lab" || $candidate->party->getName() == "DUP" || $candidate->party->getName() == "SF")
              {
                $candidate->addVotes(round($canPartyShare[$candidate->party->getName()] * $mainVotes, 0, PHP_ROUND_HALF_UP));
              } else
              {
                if((($this->region == "Northern Ireland" && !($DUPCheck == true && $SFCheck == true)) && (($DUPCheck == true && $SDLPCheck = true && $candidate->party->getName() == "SDLP")
                || ($SFCheck == true && $UUPCheck == true && $candidate->party->getName() == "UUP")
                || ($SFCheck == true && $UUPCheck == false && $candidate->party->getName() == "Alliance"))))
                {
                  $candidate->addVotes(round($canPartyShare[$candidate->party->getName()] * $mainVotes, 0, PHP_ROUND_HALF_UP));
                } else
                {
                  $candidate->addVotes(round($canPartyShare[$candidate->party->getName()] * $leftOverVotes, 0, PHP_ROUND_HALF_UP));
                }
                if(($this->region != "Northern Ireland" && !($ConCheck == true && $LabCheck == true)) && ($SNPCheck == true && $candidate->party->getName() == "SNP" && $this->region = "Scotland")
                || ($LibCheck == true && $candidate->party->getName() == "LD"))
                {
                  $candidate->addVotes(round($canPartyShare[$candidate->party->getName()] * $mainVotes, 0, PHP_ROUND_HALF_UP));
                } else
                {
                  $candidate->addVotes(round($canPartyShare[$candidate->party->getName()] * $leftOverVotes, 0, PHP_ROUND_HALF_UP));
                }
              }
            }
            $this->sortWinner(true);
        }

        public function modifyClass($classToChange, $classDetail, $change, $alignmentChange)//Class modifier not working properly, mainly dealignment is an issue.
        {
          if($this->region != "Northern Ireland" && ($classToChange == "AB" || $classToChange == "DE" || $classToChange == "C1" || $classToChange == "C2") && ($change != 0 && $change != null))
          {
            $excessVotes = 0;
            $origCanVotes = 0;
            $currentAvaliableShare = 0;
            $nextShare = 0;
            if($change > 0)
            {
              $change = ($change/100) + 1;
            } elseif($change < 0)
            {
              $change = ($change * - 1)/100;
            }
            $canParty = null;
            foreach($this->candidates as $candidate)
            {
              if((($candidate->party->getName() == "Con" && $classToChange == "AB") || ($candidate->party->getName() == "Lab" && $classToChange == "DE")) ||
              (($candidate->party->getName() == "Con" || $candidate->party->getName() == "Lab") && ($classToChange == "C1" || $classToChange == "C2")))
              {
                $canParty = $candidate->party->getName();
                $origCanVotes = $candidate->getVotes();
                if($classToChange == "C1" || $classToChange == "C2")
                {
                  if(($classToChange == "C1" && $canParty == "Lab") || ($classToChange == "C2" && $canParty == "Con"))
                  {
                    $excessVotes = round(($origCanVotes * ($classDetail / 100)) * $change * 0.35, 0, PHP_ROUND_HALF_DOWN);
                  } elseif(($classToChange == "C1" && $canParty == "Con") || ($classToChange == "C2" && $canParty == "Lab"))
                  {
                    $excessVotes = round(($origCanVotes * ($classDetail / 100)) * $change * 0.65, 0, PHP_ROUND_HALF_DOWN);
                  }
                } else
                {
                  $excessVotes = round(($origCanVotes * ($classDetail / 100)) * $change, 0, PHP_ROUND_HALF_DOWN);
                }
                if($change < 1)
                {
                  $candidate->setVotes($origCanVotes - $excessVotes);
                }
                if($change > 1)
                {
                  $excessVotes -= round($origCanVotes * ($classDetail / 100), 0, PHP_ROUND_HALF_DOWN);
                  $candidate->addVotes($excessVotes);
                }
                foreach($this->candidates as $disCandidate)
                {
                  if($disCandidate->party->getName() != $canParty)
                  {
                    $nextShare = rand(0, $excessVotes);
                    if($change < 1)
                    {
                      $disCandidate->addVotes($nextShare);
                    }
                    if($change > 1)
                    {
                      if($nextShare > $disCandidate->getVotes())
                      {
                        $nextShare = $disCandidate->getVotes();
                      }
                      $disCandidate->addVotes(($nextShare * -1));
                    }
                    $excessVotes -= $nextShare;
                  }
                }
              }
            }
          }
          if($alignmentChange != 0 && $alignmentChange != null)//THIS IS PRODUCING THE EXACT REVERSE OF WHAT IT NEEDS TO BE DOING, FIX THIS.
          {
            $alignmentChange = $alignmentChange/100;
            $excessVotes = 0;
            $excessVotePool = 0;
            $origCanVotes = 0;
            $nextShare = 0;
            $partyNotToGive = null;
            foreach($this->candidates as $candidate)
            {
              if(($candidate->party->getName() == "Con" && $classToChange == "AB") || ($candidate->party->getName() == "Lab" && $classToChange == "DE") ||
              (($candidate->party->getName() == "Con" && $candidate->party->getName() == "Lab") && ($classToChange == "C1" || $classToChange = "C2")))
              {
                $partyNotToGive = $candidate->party->getName();
                $origCanVotes = $candidate->getVotes();
                if(($classToChange == "C1" && $partyNotToGive == "Con") || ($classToChange == "C2" && $partyNotToGive == "Lab"))
                {
                  $excessVotes = round($origCanVotes * $alignmentChange * ($classDetail / 100) * 0.65, 0, PHP_ROUND_HALF_DOWN);
                } elseif(($classToChange == "C1" && $partyNotToGive == "Lab") || ($classToChange == "C2" && $partyNotToGive == "Con"))
                {
                  $excessVotes = round($origCanVotes * $alignmentChange * ($classDetail / 100) * 0.35, 0, PHP_ROUND_HALF_DOWN);
                } else
                {
                    $excessVotes = round($origCanVotes * $alignmentChange * ($classDetail / 100), 0, PHP_ROUND_HALF_DOWN);
                }
                $candidate->setVotes($origCanVotes - $excessVotes);
                $excessVotePool += $excessVotes;
              }
            }
            foreach($this->candidates as $candidate)
            {
              if($candidate->party->getName() != $partyNotToGive)
              {
                $nextShare = rand(0, $excessVotePool);
                $candidate->addVotes($nextShare);
                $excessVotePool -= $nextShare;
              }
            }
          }
          $this->sortWinner(true);
      }

      public function modifyEthnicity($groupToChange, $groupDetails, $change)
      {
        $groupVotes = 0;
        $votePool = 0;
        $excessVotes = 0;
        $origCanVotes = 0;
        if($change > 0)
        {
          $change = ((int)$change / 100) + 1;
        } elseif($change < 0)
        {
          $change = ($change * -1) / 100;
        }
        foreach($this->candidates as $candidate)
        {
          if(($candidate->party->getName() == "Con" && ($groupToChange == "White British" || $groupToChange == "Hindu" || $groupToChange == "Sikh" || $groupToChange == "Jewish"))
          || ($candidate->party->getName() == "Lab" && ($groupToChange == "Muslim" || $groupToChange == "BME"))
          || ($this->region == "Northern Ireland" && (($candidate->party->getName() == "DUP" && $groupToChange == "Catholic") || ($candidate->party->getName() == "SF" && $groupToChange == "Protestant"))))
          {
            $curCanParty = $candidate->party->getName();
            $origCanVotes = $candidate->getVotes();
            $groupVotes = round(($origCanVotes * ($groupDetails / 100)) * $change, 0, PHP_ROUND_HALF_DOWN);
            if($change > 1)
            {
              $excessVotes += round($groupVotes - ($origCanVotes * ($groupDetails / 100)), 0, PHP_ROUND_HALF_UP);
              $candidate->addVotes($excessVotes);
            } elseif($change < 1)
            {
              $origCanVotes -= $groupVotes;
              $candidate->setVotes($origCanVotes);
              $excessVotes += $groupVotes;
            }
            foreach($this->candidates as $disCandidate)
            {
              if($disCandidate->party->getName() != $curCanParty)
              {
                $votePool = rand(0, $excessVotes);
                if($change > 1)
                {
                  $disCandidate->setVotes($disCandidate->getVotes() - $votePool);
                } elseif($change < 1)
                {
                  $disCandidate->addVotes($votePool);
                }
                $excessVotes -= $votePool;
              }
            }
          }
        }
        $this->sortWinner(true);
      }

      public function modifyAge($groupToChange, $change, $groupDetails)
      {
        if($this->region != "Northern Ireland")
        {
          $highAge = 0;
          $lowAge = 0;
          $votesPerAge = 0;
          $votePool = 0;
          $excessVotes = 0;
          $chanceToVoteAlg = null;
          if($change < 0)
          {
            $change = ($change * -1) / 100;
          }
          if($change > 0)
          {
            $change = 1 + ($change / 100);
          }
          if($groupToChange == "65+")
          {
            $highAge = 73;
            $lowAge = 65;
          } else
          {
            $lowAge = (int)substr($groupToChange, 0, 2);
            $highAge = (int)substr($groupToChange, 3, 2);
          }
          foreach($this->candidates as $candidate)
          {
            if($change > 1)
            {
              $excessVotes = round(($candidate->getVotes() * ($groupDetails / 100) * $change) - ($candidate->getVotes() * ($groupDetails / 100)), 0, PHP_ROUND_HALF_DOWN);
              $candidate->setVotes($candidate->getVotes() - $excessVotes);
              $votePool += $excessVotes;
            }
          }
          $votesPerAge = round($votePool / ($highAge - $lowAge), 0, PHP_ROUND_HALF_DOWN);
          for($i = $lowAge; $i <= $highAge; $i++)
          {
            if($i < 47)
            {
              $chanceToVoteAlg = 0.5 - ((47 - $i) / 10) * 0.09;
            }
            if($i > 47)
            {
              $chanceToVoteAlg = 0.5 - (($i - 47) / 10) * 0.09;
            }
            $votePool = round($votesPerAge, 0, PHP_ROUND_HALF_DOWN);
            foreach($this->candidates as $candidate)
            {
              if($change > 1)
              {
                if(($candidate->party->getName() == "Con" && $i > 47) || ($candidate->party->getName() == "Lab" && $i < 47))
                {
                  $candidate->addVotes(round($votesPerAge * (1 - $chanceToVoteAlg), 0, PHP_ROUND_HALF_DOWN));
                } elseif(($candidate->party->getName() == "Con" && $i < 47) || ($candidate->party->getName() == "Lab" && $i > 47))
                {
                  $candidate->addVotes(round($votesPerAge * $chanceToVoteAlg, 0, PHP_ROUND_HALF_DOWN));
                }
              }
              if($change < 1)
              {
                if($i != 18)
                {
                  $excessVotes = round(($votesPerAge / 2) / ($lowAge - 17), 0, PHP_ROUND_HALF_DOWN);//($lowAge - 17)
                  for($x = 18; $x < $i; $x++)
                  {
                    $chanceToVoteAlg = 0.5 - ((47 - $x) / 10) * 0.09;
                    foreach($this->candidates as $disCandidate)
                    {
                      if($candidate->party->getName() == "Con")
                      {
                        $candidate->addVotes(round($excessVotes * $chanceToVoteAlg), 0, PHP_ROUND_HALF_DOWN);
                      }
                      if($candidate->party->getName() == "Lab")
                      {
                        $candidate->addVotes(round($excessVotes * (1 - $chanceToVoteAlg)), 0, PHP_ROUND_HALF_DOWN);
                      }
                    }
                  }
                }
                if($i != 73)
                {
                  $excessVotes = round(($votesPerAge / 2) / (74 - $highAge), 0, PHP_ROUND_HALF_DOWN);//(74 - $highAge)
                  for($x = $highAge; $x < 73; $x++)
                  {
                    $chanceToVoteAlg = 0.5 - (($x - 47) / 10) * 0.09;
                    foreach($this->candidates as $disCandidate)
                    {
                      if($candidate->party->getName() == "Con")
                      {
                        $candidate->addVotes(round($excessVotes * (1 - $chanceToVoteAlg)), 0, PHP_ROUND_HALF_DOWN);
                      }
                      if($candidate->party->getName() == "Lab")
                      {
                        $candidate->addVotes(round($excessVotes * $chanceToVoteAlg), 0, PHP_ROUND_HALF_DOWN);
                      }
                    }
                  }
                }
              foreach($this->candidates as $disCandidate)
              {
                if($disCandidate->party->getName() != "Con" || $disCandidate->party->getName() != "Lab")
                {
                  if($change > 1)
                  {
                    $excessVotes = -1 * (rand(0, $votePool));
                    $votePool -= (-1 * $excessVotes);
                  }
                  if($change < 1)
                  {
                    $excessVotes = rand(0, $votePool);
                    $votePool -= $excessVotes;
                  }
                  $disCandidate->addVotes($excessVotes);
                }
              }
            }
          }
        }
        $this->sortWinner(true);
      }
    }

      public function jsonSerialize()
      {
        return
        [
          'name' => $this->name,
          'electorate' => $this->electorate,
          'turnoutNumber' => $this->turnout[0],
          'turnoutPercentage' => $this->turnout[1],
          'candidates' => $this->candidates
        ];
      }
    }

      class DataControl
      {
        public static $conn;
        private static $search;

        public static function DHondt($parties, $numOfSeats, $returnArr)
        {
          if($numOfSeats == 0)
          {
            return $returnArr;
          }
          $maxVotes = 0;
          $maxParty = "";
          $numSeatsPlus1 = 0;
          foreach($parties as $party => $votes)
          {
            if($votes > $maxVotes)
            {
              $maxParty = $party;
              $maxVotes = $votes;
            }
          }
          $returnArr[] = $maxParty;
          foreach($returnArr as $party)
          {
            if($party == $maxParty)
            {
              $numSeatsPlus1++;
            }
          }
          $numSeatsPlus1++;
          $parties[$maxParty] = round($maxVotes/$numSeatsPlus1, 0, PHP_ROUND_HALF_UP);
          $numOfSeats -= 1;
          return self::DHondt($parties, $numOfSeats, $returnArr);
        }

        public static function binarySearch($arr, $start, $end, $value)
        {
          if($start > $end)
          {
            return "No value found - failed to find $value";
          }
          if($start == $end || $end - $start == 1)
          {
            if($arr[$start]->getName(true) == $value)
            {
              return $arr[$start];
            }
          }
          $valueCheck = floor(($end + $start)/2);
          if($arr[$valueCheck]->getName(true) == $value)
          {
            return $arr[$valueCheck];
          } elseif($arr[$valueCheck]->getName(true) > $value)
          {
              return self::binarySearch($arr, $start, $valueCheck - 1, $value);
          } else
          {
              return self::binarySearch($arr, $valueCheck + 1, $end, $value);
          }
        }

        public static function readCSV($fileName)
        {
          $file = fopen($fileName.".csv", "r");
          $returnArr = Array();
          while (($row = fgetcsv($file, 0, ",")) != false)
          {
            $returnArr[] = $row;
          }
          fclose($file);
          return $returnArr;
        }

        public static function checkByRegex($regex, $data)
        {
          if(preg_match($regex, $data))
          {
            return true;
          } else
          {
            return false;
          }
        }

        public static function mergeSort($arr, $case)
        {
          if(count($arr) <= 1) return $arr;
          $mid = intdiv(count($arr), 2);
          $left = array_slice($arr, 0, $mid);
          $right = array_slice($arr, $mid);
          $left = self::mergeSort($left, $case);
          $right = self::mergeSort($right, $case);
          return self::merge($left, $right, $case);
        }

        private function merge($left, $right, $case)
        {
          $tempArr = Array();
          while(count($left) > 0 && count($right) > 0)
          {
            switch($case)
            {
              case 1:
                if($left[0]->getSeats() > $right[0]->getSeats())
                {
                  $tempArr[] = $left[0];
                  $left = array_slice($left, 1);
                } else
                {
                  $tempArr[] = $right[0];
                  $right = array_slice($right, 1);
                }
                break;
              case 2:
                if($left[0]->getVotes() > $right[0]->getVotes())
                {
                  $tempArr[] = $left[0];
                  $left = array_slice($left, 1);
                } else
                {
                  $tempArr[] = $right[0];
                  $right = array_slice($right, 1);
                }
                break;
              case 3:
                if($left[0]->getName() < $right[0]->getName())
                {
                  $tempArr[] = $left[0];
                  $left = array_slice($left, 1);
                } else
                {
                  $tempArr[] = $right[0];
                  $right = array_slice($right, 1);
                }
                break;
            }
          }
          while(count($left) > 0)
          {
            $tempArr[] = $left[0];
            $left = array_slice($left, 1);
          }
          while(count($right) > 0)
          {
            $tempArr[] = $right[0];
            $right = array_slice($right, 1);
          }
          return $tempArr;
        }

        public static function makeRequest($APIRequest)
        {
          $curl;
          $APIReturn;
          $userReturn;
          $errorMsg;
          $curl = curl_init();
          curl_setopt($curl, CURLOPT_URL, $APIRequest);
          curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
          curl_setopt($curl, CURLOPT_FAILONERROR, true);
          $APIReturn = curl_exec($curl);
          if (curl_error($curl))
          {
            $errorMsg = curl_error($curl);
          }
          $userReturn = json_decode($APIReturn);
          curl_close($curl);
          if (isset($errorMsg))
          {
            echo "Error ".$errorMsg;
          }
          return $userReturn;
        }

        public static function makeDBRequest($sqlStr)
        {
          self::$search = self::$conn->query($sqlStr);
          if (!self::$search)
          {
            trigger_error("Invalid search: ".self::$conn->error);
          }
        }

        public static function returnNext()
        {
          if (self::$search->num_rows > 0){
            return self::$search->fetch_assoc();
          } else
          {
            return false;
          }
        }

        public function __destruct()
        {
          self::$conn->close();
        }
      }
    ?>
  </body>
</html>
