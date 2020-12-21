//
// Created by c41 on 21/12/2020.
//
#ifndef ELECTION_SIMULATOR_ELECTION_H
#define ELECTION_SIMULATOR_ELECTION_H

#include <vector>
#include <string>

class Modifier;

class Election {
private:
    std::vector<int> parties;
    std::vector<int> constituencies;
    std::vector<int> regions;
    int year;
    int electorateSize;
    int rawTurnout;
    int DDPECode;
    std::string description;

    Election(int electionYear, std::vector<Modifier> modifiers);
    void setUpConstituencies();
    void setUpParties();
    void setUpRegions();
    void findConstituencyWinners();
    void computeElection();

};


#endif //ELECTION_SIMULATOR_ELECTION_H
