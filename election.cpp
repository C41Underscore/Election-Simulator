//
// Created by c41 on 21/12/2020.
//

#include "election.h"

Election::Election(int electionYear, std::vector<Modifier> modifiers)
{
    year = electionYear;
    if(year == 2010)
    {
        DDPECode = 382037;
    }
    else if(year == 2015)
    {
        DDPECode = 382386;
    }
    else if(year == 2017)
    {
        DDPECode = 730039;
    }
    setUpConstituencies();
    setUpParties();
    setUpRegions();
}
