import json
import requests


def fetch_data_for_election(election_id):
    url = "http://lda.data.parliament.uk/electionresults.json?electionId=" + str(election_id) + "&_pageSize=650"
    response = requests.get(url).json()
    curated_json = {}
    for i in response["result"]["items"]:
        current_con = i["constituency"]["label"]["_value"]
        current_con_code = i["_about"].split("/")[-1]
        curated_json[current_con] = {
            "electorate_size": i["electorate"]
        }
        con_result_url = "http://lda.data.parliament.uk/electionresults/" + current_con_code + ".json"
        con_details = requests.get(con_result_url).json()
        con_candidate_list = []
        for j in con_details["result"]["primaryTopic"]["candidate"]:
            candidate = {
                "name": j["fullName"]["_value"],
                "votes": j["numberOfVotes"],
                "party": j["party"]["_value"]
            }
            con_candidate_list.append(candidate)
        curated_json[current_con]["candidates"] = con_candidate_list
    return json.dumps(curated_json)


if __name__ == "__main__":
    # 2010 election code
    fetch_data_for_election(382386)