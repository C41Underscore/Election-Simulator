from flask import Flask
from os import mkdir
from os.path import isdir
import election_functions

app = Flask(__name__)

election_codes = {
    "2010": 382037,
    "2015": 382386,
    "2017": 730039
}

@app.route("/")
def hello_world():
    return "<p>Hello, World!</p>"

@app.route("/election/<year>")
def load_election_page(year):
    # Get data from respective JSON file and load HTML page using JSON to fill in template
    return "<p>wh0?</p>"


if __name__ == "__main__":
    if not isdir("elections"):
        mkdir("elections")
    for election, code in election_codes.items():
        election_json = election_functions.fetch_data_for_election(code)
        with open("elections/" + election + ".json", "w") as file:
            file.write(election_json)